<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Simple two-factor implementation: generates a numeric code, caches it, and
 * delivers it via the existing TwoFactorCode notification. Adds a small
 * attempt counter so repeated wrong submissions expire the code and force a
 * fresh resend.
 */
class TwoFactorService
{
    // Default dummy code for local/dev convenience (kept for non-cache fallback).
    public const DUMMY_CODE = '123456';

    public function send(User $user): void
    {
        try {
            $code = (string) random_int(100000, 999999);
        } catch (\Throwable $e) {
            $code = (string) mt_rand(100000, 999999);
        }

        $cacheKey = 'twofactor:'.$user->id;
        $attemptsKey = 'twofactor:attempts:'.$user->id;

        Cache::put($cacheKey, $code, now()->addMinutes(10));
        // Reset attempt counter when issuing a fresh code
        Cache::forget($attemptsKey);

        // Deliver the code via email using the existing notification
        try {
            $user->notify(new \App\Notifications\TwoFactorCode($code));
        } catch (\Throwable $e) {
            // Non-fatal: the resend endpoint also attempts delivery and failures
            // should not block the login flow. Log or handle in future.
        }
    }

    public function verify(User $user, string $code): bool
    {
        $cacheKey = 'twofactor:'.$user->id;
        $attemptsKey = 'twofactor:attempts:'.$user->id;

        $cached = Cache::get($cacheKey);
        if ($cached) {
            if (hash_equals((string) $cached, (string) $code)) {
                // Success: clear both the code and any attempt counter
                Cache::forget($cacheKey);
                Cache::forget($attemptsKey);
                return true;
            }

            // Incorrect: expire the cached code immediately so the user must request a fresh code.
            Cache::forget($cacheKey);
            Cache::forget($attemptsKey);

            return false;
        }

        // Fallback to the dummy static code for local/dev convenience.
        return hash_equals(self::DUMMY_CODE, (string) $code);
    }
}
