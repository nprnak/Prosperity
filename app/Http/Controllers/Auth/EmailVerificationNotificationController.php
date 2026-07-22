<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $request->user()->sendEmailVerificationNotification();

        // 'status' is a machine key the page switches on; 'success' is what the
        // toast shows, so it has to be a sentence.
        return back()
            ->with('status', 'verification-link-sent')
            ->with('success', 'A fresh verification link has been sent to your email address.');
    }
}
