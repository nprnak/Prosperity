<?php

namespace Modules\ApplicantManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum Gender: string implements HasLabels
{
    use HasOptions;

    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function labelEn(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Other => 'Other',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Male => 'पुरुष',
            self::Female => 'महिला',
            self::Other => 'अन्य',
        };
    }
}
