<?php

namespace App\Services;

use App\Mail\OperatorAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends operator alert emails, de-duplicated per alert key.
 *
 * A failure that repeats every night must produce one email, not one per night,
 * so the first call for a key sends and every subsequent call for that same key
 * is suppressed until the window expires.
 */
class AlertNotifier
{
    public function __construct(
        private readonly ?string $recipient = null,
        private readonly ?int $suppressionMinutes = null,
    ) {}

    /**
     * Send an alert for the given key unless one was already sent inside the window.
     */
    public function notify(string $key, string $message): void
    {
        $reserved = Cache::add(
            $this->cacheKey($key),
            true,
            now()->addMinutes($this->window()),
        );

        if (! $reserved) {
            return;
        }

        $recipient = $this->recipient();

        /*
         * Callers alert from inside a catch block and then rethrow the original
         * failure. If this method threw, it would replace that failure with its
         * own — the operator would get no email AND a misleading error about
         * mail headers instead of the export error that actually happened. The
         * alerting path would become the thing that hides the problem, which is
         * precisely the false confidence alerting exists to prevent.
         *
         * So every failure here is logged and swallowed, and the de-dup
         * reservation is released so the next run can try again rather than
         * being suppressed for the rest of the window by a send that never
         * happened.
         */
        if ($recipient === '') {
            Log::error('AlertNotifier has no configured recipient; the alert was dropped.', [
                'alert_key' => $key,
                'alert_message' => $message,
                'fix' => 'Set ALERT_MAIL_TO in the environment.',
            ]);

            Cache::forget($this->cacheKey($key));

            return;
        }

        try {
            Mail::to($recipient)->send(new OperatorAlert($key, $message));
        } catch (Throwable $e) {
            Log::error('AlertNotifier failed to send an operator alert.', [
                'alert_key' => $key,
                'alert_message' => $message,
                'exception' => $e->getMessage(),
            ]);

            Cache::forget($this->cacheKey($key));
        }
    }

    private function cacheKey(string $key): string
    {
        return 'alert-notifier:'.sha1($key);
    }

    private function recipient(): string
    {
        return $this->recipient ?? (string) config('mail.alerts.recipient');
    }

    /**
     * A window of zero would send one email per failure, which is the exact
     * behaviour de-duplication exists to prevent — a nightly job broken for a
     * week would produce seven emails and train the operator to ignore them. An
     * unset or empty ALERT_SUPPRESSION_MINUTES casts to 0, so the floor is
     * enforced here rather than trusted to configuration.
     */
    private function window(): int
    {
        return max(1, $this->suppressionMinutes ?? (int) config('mail.alerts.suppression_minutes'));
    }
}
