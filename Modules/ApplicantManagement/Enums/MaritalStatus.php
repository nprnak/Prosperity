<?php

namespace Modules\ApplicantManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum MaritalStatus: string implements HasLabels
{
    use HasOptions;

    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    public function labelEn(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Married => 'Married',
            self::Divorced => 'Divorced',
            self::Widowed => 'Widowed',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Single => 'अविवाहित',
            self::Married => 'विवाहित',
            self::Divorced => 'सम्बन्धविच्छेद',
            self::Widowed => 'विधुर / विधवा',
        };
    }
}
