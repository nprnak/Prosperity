<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum JarLotItemStatus: string implements HasLabels
{
    use HasOptions;

    case Loaded = 'loaded';
    case Delivered = 'delivered';
    case ReturnedUndelivered = 'returned_undelivered';

    public function labelEn(): string
    {
        return match ($this) {
            self::Loaded => 'Loaded',
            self::Delivered => 'Delivered',
            self::ReturnedUndelivered => 'Returned Undelivered',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Loaded => 'लोड भएको',
            self::Delivered => 'डेलिभर भएको',
            self::ReturnedUndelivered => 'नबिकी फिर्ता',
        };
    }
}
