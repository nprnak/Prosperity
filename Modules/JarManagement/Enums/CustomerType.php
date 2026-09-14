<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum CustomerType: string implements HasLabels
{
    use HasOptions;

    case Household = 'household';
    case Dealer = 'dealer';

    public function labelEn(): string
    {
        return match ($this) {
            self::Household => 'Household',
            self::Dealer => 'Dealer',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Household => 'घरधुरी',
            self::Dealer => 'डिलर',
        };
    }
}
