<?php

namespace App\Models;

use App\Notifications\VerifyEmailWithOtp;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_otp_code',
        'email_otp_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_otp_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Sends the verification mail with both a signed link and a 6-digit OTP.
     * The OTP is stored hashed and expires after 10 minutes.
     */
    public function sendEmailVerificationNotification(): void
    {
        $otp = (string) random_int(100000, 999999);

        $this->forceFill([
            'email_otp_code' => Hash::make($otp),
            'email_otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->notify(new VerifyEmailWithOtp($otp));
    }

    /**
     * Staff accounts must pass a second factor at login; applicants are exempt.
     */
    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole([
            'super_admin', 'finance_staff',
            'profile_verifier', 'profile_reviewer', 'profile_approver',
            'application_verifier', 'application_reviewer', 'application_approver',
        ]);
    }

    public function clearEmailOtp(): void
    {
        $this->forceFill([
            'email_otp_code' => null,
            'email_otp_expires_at' => null,
        ])->save();
    }
}
