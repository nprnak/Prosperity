<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum PaymentStatus: string implements HasLabels
{
    use HasOptions;

    case Paid = 'paid';
    case Partial = 'partial';
    case Unpaid = 'unpaid';

    public function labelEn(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Partial => 'Partially Paid',
            self::Unpaid => 'Unpaid',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Paid => 'भुक्तानी भएको',
            self::Partial => 'आंशिक भुक्तानी',
            self::Unpaid => 'भुक्तानी नभएको',
        };
    }
}
