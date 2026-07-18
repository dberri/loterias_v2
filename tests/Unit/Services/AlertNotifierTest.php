<?php

namespace Tests\Unit\Services;

use App\Mail\OperatorAlert;
use App\Services\AlertNotifier;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AlertNotifierTest extends TestCase
{
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
