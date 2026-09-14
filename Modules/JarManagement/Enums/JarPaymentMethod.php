<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

/**
 * Named "Jar" prefixed to avoid any confusion with the share-management
 * module's own PaymentManagement\Enums equivalent — this is an unrelated,
 * much simpler, cash-at-the-doorstep concept.
 */
enum JarPaymentMethod: string implements HasLabels
{
    use HasOptions;

    case Cash = 'cash';
    case Due = 'due';
    case Other = 'other';

    public function labelEn(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Due => 'On Credit (Due)',
            self::Other => 'Other',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Cash => 'नगद',
            self::Due => 'उधारो',
            self::Other => 'अन्य',
        };
    }
}
