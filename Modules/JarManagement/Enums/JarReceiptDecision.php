<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum JarReceiptDecision: string implements HasLabels
{
    use HasOptions;

    case Accepted = 'accepted';
    case Quarantined = 'quarantined';

    public function labelEn(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::Quarantined => 'Quarantined',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Accepted => 'स्वीकृत',
            self::Quarantined => 'क्वारेन्टाइन',
        };
    }
}
