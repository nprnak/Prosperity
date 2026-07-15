<?php

namespace Modules\VoucherManagement\Policies;

use App\Models\User;
use Modules\VoucherManagement\Models\Voucher;

class VoucherPolicy
{
    /**
     * Applicants may only download vouchers belonging to their own
     * application; `voucher.download-any` (approver, admin) bypasses
     * the ownership check.
     */
    public function download(User $user, Voucher $voucher): bool
    {
        if ($user->can('voucher.download-any')) {
            return true;
        }

        return $user->can('voucher.download')
            && $voucher->paymentTransaction?->shareApplication?->applicant?->user_id === $user->id;
    }
}
