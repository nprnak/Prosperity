<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum JarCondition: string implements HasLabels
{
    use HasOptions;

    case Good = 'good';
    case Damaged = 'damaged';
    case Leaking = 'leaking';
    case CapMissing = 'cap_missing';
    case Other = 'other';

    public function labelEn(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Damaged => 'Damaged',
            self::Leaking => 'Leaking',
            self::CapMissing => 'Cap Missing',
            self::Other => 'Other',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Good => 'ठीक',
            self::Damaged => 'बिग्रिएको',
            self::Leaking => 'चुहावट',
            self::CapMissing => 'बिर्को हराएको',
            self::Other => 'अन्य',
        };
    }
}
