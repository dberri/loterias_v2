<?php

namespace Tests\Unit\Services\AlertNotifierTest;

use App\Mail\OperatorAlert;
use App\Services\AlertNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Callers alert from inside a catch block and then rethrow the original
 * failure. A throw from here would replace that failure with its own, so
 * the operator would get no email AND a misleading error instead of the one
 * that actually happened — the alerting path would hide the very problem it
 * exists to report. CI caught this on its first run, because .env.example
 * ships a blank ALERT_MAIL_TO and Mail::to('') throws.
 */
test('a blank recipient is logged and dropped rather than thrown', function () {
    Mail::fake();
    Log::spy();

    (new AlertNotifier('', 60))->notify('export-failed', 'checksum mismatch');

    Mail::assertNothingSent();
    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'no configured recipient'))
        ->once();
});

test('a send that throws is logged and swallowed', function () {
    Log::spy();
    Mail::shouldReceive('to->send')->andThrow(new RuntimeException('smtp is down'));

    (new AlertNotifier('ops@example.com', 60))->notify('export-failed', 'checksum mismatch');

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'failed to send'))
        ->once();
});

/**
 * A send that never happened must not consume the de-dup window. Otherwise
 * one blank-recipient night suppresses every real alert for the rest of the
 * window — a week, by default — long after the config is fixed.
 */
test('a dropped alert does not suppress the next attempt', function () {
    Log::spy();
    (new AlertNotifier('', 60))->notify('export-failed', 'checksum mismatch');

    Mail::fake();
    (new AlertNotifier('ops@example.com', 60))->notify('export-failed', 'checksum mismatch');

    Mail::assertSentCount(1);
});

/**
 * An unset or empty ALERT_SUPPRESSION_MINUTES casts to 0, which would send
 * one email per failure and train the operator to ignore them.
 */
test('a zero suppression window still deduplicates', function () {
    Mail::fake();

    $notifier = new AlertNotifier('ops@example.com', 0);
    $notifier->notify('export-failed', 'first');
    $notifier->notify('export-failed', 'second');

    Mail::assertSentCount(1);
});

test('first call for a key sends an email', function () {
    Mail::fake();

    (new AlertNotifier('ops@example.com', 60))->notify('export-failed', 'checksum mismatch');

    Mail::assertSentCount(1);
});

test('the alert email carries the message and is addressed to the configured recipient', function () {
    Mail::fake();

    (new AlertNotifier('ops@example.com', 60))->notify('export-failed', 'checksum mismatch');

    Mail::assertSent(OperatorAlert::class, function (OperatorAlert $mailable): bool {
        return $mailable->hasTo('ops@example.com')
            && $mailable->alertKey === 'export-failed'
            && str_contains($mailable->render(), 'checksum mismatch');
    });
});

test('repeat call with the same key inside the window sends nothing', function () {
    Mail::fake();
    $notifier = new AlertNotifier('ops@example.com', 60);

    $notifier->notify('export-failed', 'first failure');
    $notifier->notify('export-failed', 'second failure');

    Mail::assertSentCount(1);
});

test('a failure repeating every night for a week sends one email', function () {
    Mail::fake();
    $notifier = new AlertNotifier('ops@example.com', 10080);

    for ($night = 0; $night < 7; $night++) {
        $this->travel(1)->days();
        $notifier->notify('export-failed', "nightly failure {$night}");
    }

    Mail::assertSentCount(1);
});

test('sending resumes once the window expires', function () {
    Mail::fake();
    $notifier = new AlertNotifier('ops@example.com', 60);

    $notifier->notify('export-failed', 'first failure');
    $this->travel(61)->minutes();
    $notifier->notify('export-failed', 'later failure');

    Mail::assertSentCount(2);
});

test('the suppression window is configurable', function () {
    Mail::fake();
    $notifier = new AlertNotifier('ops@example.com', 5);

    $notifier->notify('export-failed', 'first failure');
    $this->travel(6)->minutes();
    $notifier->notify('export-failed', 'later failure');

    Mail::assertSentCount(2);
});

test('the window falls back to configuration when not supplied', function () {
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
});

test('distinct keys never suppress each other', function () {
    Mail::fake();
    $notifier = new AlertNotifier('ops@example.com', 10080);

    $notifier->notify('export-failed', 'export broke');
    $notifier->notify('scrape-failed', 'scrape broke');
    $notifier->notify('export-failed', 'export broke again');

    Mail::assertSentCount(2);
});
