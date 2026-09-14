<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum ReconciliationStatus: string implements HasLabels
{
    use HasOptions;

    case Settled = 'settled';
    case Outstanding = 'outstanding';
    case Excess = 'excess';

    public function labelEn(): string
    {
        return match ($this) {
            self::Settled => 'Settled',
            self::Outstanding => 'Outstanding Jars',
            self::Excess => 'Excess Returned',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Settled => 'मिलान भएको',
            self::Outstanding => 'बाँकी जार',
            self::Excess => 'बढी फिर्ता',
        };
    }
}
