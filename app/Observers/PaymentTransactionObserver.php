<?php

namespace App\Observers;

use Modules\PaymentManagement\Models\PaymentTransaction;

class PaymentTransactionObserver
{
    public function updating(PaymentTransaction $payment): void
    {
        if ($payment->isDirty('verification_status') && $payment->verification_status === 'verified' && ! $payment->verified_at) {
            $payment->verified_at = now();
        }
    }
}
