<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum VehicleLotStatus: string implements HasLabels
{
    use HasOptions;

    case Loading = 'loading';
    case Dispatched = 'dispatched';
    case Returned = 'returned';
    case Reconciled = 'reconciled';

    public function labelEn(): string
    {
        return match ($this) {
            self::Loading => 'Loading',
            self::Dispatched => 'Dispatched',
            self::Returned => 'Returned to Factory',
            self::Reconciled => 'Reconciled',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Loading => 'लोड हुँदै',
            self::Dispatched => 'पठाइएको',
            self::Returned => 'कारखानामा फर्केको',
            self::Reconciled => 'मिलान भएको',
        };
    }
}
