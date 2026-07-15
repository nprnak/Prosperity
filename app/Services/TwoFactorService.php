<?php

namespace App\Services;

use App\Models\User;

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
    public const DUMMY_CODE = '1234';

    public function send(User $user): void
    {
        // TODO: generate a per-user code and deliver it via mail.
    }

    public function verify(User $user, string $code): bool
    {
        return $code === self::DUMMY_CODE;
    }
}
