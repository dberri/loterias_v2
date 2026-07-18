<?php

namespace App\Services;

use App\Mail\OperatorAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

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

        Mail::to($this->recipient())->send(new OperatorAlert($key, $message));
    }

    private function cacheKey(string $key): string
    {
        return 'alert-notifier:'.sha1($key);
    }

    private function recipient(): string
    {
        return $this->recipient ?? (string) config('mail.alerts.recipient');
    }

    private function window(): int
    {
        return $this->suppressionMinutes ?? (int) config('mail.alerts.suppression_minutes');
    }
}
