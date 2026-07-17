<?php

namespace Modules\VoucherManagement\Repositories;

use App\Repositories\Repository;
use Modules\VoucherManagement\Models\Voucher;

class VoucherRepository extends Repository
{
    public function __construct(Voucher $model)
    {
        parent::__construct($model);
    }

    public function findByVerificationCode(string $code): ?Voucher
    {
        return $this->query()
            ->where('verification_code', $code)
            ->with('paymentTransaction.shareApplication')
            ->first();
    }
}
