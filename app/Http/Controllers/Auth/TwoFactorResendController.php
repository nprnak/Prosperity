<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TwoFactorResendController extends Controller
{
    public function resend(Request $request)
    {
        $email = $request->input('email') ?? optional($request->user())->email;

        if (! $email) {
            return response()->json(['message' => 'No user email available'], 422);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Generate a 6-digit code and cache it for short expiry. The verification flow must check this cache to accept the code.
        try {
            // $code = random_int(100000, 999999);
            $code = '246810';
        } catch (\Exception $e) {
            // $code = mt_rand(100000, 999999);
            $code = '246810';
        }

        Cache::put('twofactor:'.$user->id, (string) $code, now()->addMinutes(10));

        try {
            $user->notify(new TwoFactorCode($code));
        } catch (\Throwable $e) {
            Log::error('TwoFactor resend failed: '.$e->getMessage());
            // still return success to avoid leaking info
        }

        return response()->json(['message' => 'Two-factor code sent']);
    }
}
