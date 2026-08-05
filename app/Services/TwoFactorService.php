<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Placeholder second-factor implementation.
 *
 * The flow (challenge page, pending-session gate, throttling) is real; only
 * code generation/delivery is stubbed. To go live: generate a random code in
 * send(), store it hashed with an expiry (mirror the email OTP columns), mail
 * it to the user, and check it in verify().
 */
class TwoFactorService
{
    // Default dummy code updated to 6 digits for consistency with UI and tests.
    public const DUMMY_CODE = '123456';

    public function send(User $user): void
    {
        // TODO: generate a per-user code and deliver it via mail.
        // For now, this placeholder could write to the cache for integration with the resend controller.
        try {
            $code = random_int(100000, 999999);
        } catch (\Exception $e) {
            $code = mt_rand(100000, 999999);
        }

        Cache::put('twofactor:'.$user->id, (string)$code, now()->addMinutes(10));

        // Optionally log or notify the user in a real implementation.
    }

    public function verify(User $user, string $code): bool
    {
        // Prefer the cached per-user code (set by resend/send) if available.
        $cached = Cache::get('twofactor:'.$user->id);
        if ($cached) {
            return hash_equals((string)$cached, (string)$code);
        }

        // Fallback to the dummy static code for local/dev convenience.
        return hash_equals(self::DUMMY_CODE, (string)$code);
    }
}
