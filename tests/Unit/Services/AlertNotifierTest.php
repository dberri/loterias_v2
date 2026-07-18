<?php

namespace Tests\Unit\Services;

use App\Mail\OperatorAlert;
use App\Services\AlertNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class AlertNotifierTest extends TestCase
{
    /**
     * Callers alert from inside a catch block and then rethrow the original
     * failure. A throw from here would replace that failure with its own, so
     * the operator would get no email AND a misleading error instead of the one
     * that actually happened — the alerting path would hide the very problem it
     * exists to report. CI caught this on its first run, because .env.example
     * ships a blank ALERT_MAIL_TO and Mail::to('') throws.
     */
    public function test_a_blank_recipient_is_logged_and_dropped_rather_than_thrown(): void
    {
        Mail::fake();
        Log::spy();

        (new AlertNotifier('', 60))->notify('export-failed', 'checksum mismatch');

        Mail::assertNothingSent();
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message): bool => str_contains($message, 'no configured recipient'))
            ->once();
    }

    public function test_a_send_that_throws_is_logged_and_swallowed(): void
    {
        Log::spy();
        Mail::shouldReceive('to->send')->andThrow(new RuntimeException('smtp is down'));

        (new AlertNotifier('ops@example.com', 60))->notify('export-failed', 'checksum mismatch');

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message): bool => str_contains($message, 'failed to send'))
            ->once();
    }

    /**
     * A send that never happened must not consume the de-dup window. Otherwise
     * one blank-recipient night suppresses every real alert for the rest of the
     * window — a week, by default — long after the config is fixed.
     */
    public function test_a_dropped_alert_does_not_suppress_the_next_attempt(): void
    {
        Log::spy();
        (new AlertNotifier('', 60))->notify('export-failed', 'checksum mismatch');

        Mail::fake();
        (new AlertNotifier('ops@example.com', 60))->notify('export-failed', 'checksum mismatch');

        Mail::assertSentCount(1);
    }

    /**
     * An unset or empty ALERT_SUPPRESSION_MINUTES casts to 0, which would send
     * one email per failure and train the operator to ignore them.
     */
    public function test_a_zero_suppression_window_still_deduplicates(): void
    {
        Mail::fake();

        $notifier = new AlertNotifier('ops@example.com', 0);
        $notifier->notify('export-failed', 'first');
        $notifier->notify('export-failed', 'second');

        Mail::assertSentCount(1);
    }

    public function test_first_call_for_a_key_sends_an_email(): void
    {
        Mail::fake();

        (new AlertNotifier('ops@example.com', 60))->notify('export-failed', 'checksum mismatch');

        Mail::assertSentCount(1);
    }

    public function test_the_alert_email_carries_the_message_and_is_addressed_to_the_configured_recipient(): void
    {
        Mail::fake();

        (new AlertNotifier('ops@example.com', 60))->notify('export-failed', 'checksum mismatch');

        Mail::assertSent(OperatorAlert::class, function (OperatorAlert $mailable): bool {
            return $mailable->hasTo('ops@example.com')
                && $mailable->alertKey === 'export-failed'
                && str_contains($mailable->render(), 'checksum mismatch');
        });
    }

    public function test_repeat_call_with_the_same_key_inside_the_window_sends_nothing(): void
    {
        Mail::fake();
        $notifier = new AlertNotifier('ops@example.com', 60);

        $notifier->notify('export-failed', 'first failure');
        $notifier->notify('export-failed', 'second failure');

        Mail::assertSentCount(1);
    }

    public function test_a_failure_repeating_every_night_for_a_week_sends_one_email(): void
    {
        Mail::fake();
        $notifier = new AlertNotifier('ops@example.com', 10080);

        for ($night = 0; $night < 7; $night++) {
            $this->travel(1)->days();
            $notifier->notify('export-failed', "nightly failure {$night}");
        }

        Mail::assertSentCount(1);
    }

    public function test_sending_resumes_once_the_window_expires(): void
    {
        Mail::fake();
        $notifier = new AlertNotifier('ops@example.com', 60);

        $notifier->notify('export-failed', 'first failure');
        $this->travel(61)->minutes();
        $notifier->notify('export-failed', 'later failure');

        Mail::assertSentCount(2);
    }

    public function test_the_suppression_window_is_configurable(): void
    {
        Mail::fake();
        $notifier = new AlertNotifier('ops@example.com', 5);

        $notifier->notify('export-failed', 'first failure');
        $this->travel(6)->minutes();
        $notifier->notify('export-failed', 'later failure');

        Mail::assertSentCount(2);
    }

    public function test_the_window_falls_back_to_configuration_when_not_supplied(): void
    {
        Mail::fake();
        config(['mail.alerts.suppression_minutes' => 30, 'mail.alerts.recipient' => 'config@example.com']);
        $notifier = new AlertNotifier;

        $notifier->notify('export-failed', 'first failure');
        $this->travel(29)->minutes();
        $notifier->notify('export-failed', 'suppressed');
        $this->travel(2)->minutes();
        $notifier->notify('export-failed', 'sent again');

        Mail::assertSentCount(2);
        Mail::assertSent(OperatorAlert::class, fn (OperatorAlert $mailable): bool => $mailable->hasTo('config@example.com'));
    }

    public function test_distinct_keys_never_suppress_each_other(): void
    {
        Mail::fake();
        $notifier = new AlertNotifier('ops@example.com', 10080);

        $notifier->notify('export-failed', 'export broke');
        $notifier->notify('scrape-failed', 'scrape broke');
        $notifier->notify('export-failed', 'export broke again');

        Mail::assertSentCount(2);
    }
}
