<?php

namespace Modules\ApplicantManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

/**
 * Backing values keep their trailing dots because they are printed verbatim
 * on the English half of the share application form.
 */
enum Title: string implements HasLabels
{
    use HasOptions;

    case Mr = 'Mr.';
    case Mrs = 'Mrs.';
    case Ms = 'Ms.';

    public function labelEn(): string
    {
        return $this->value;
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Mr => 'श्री',
            self::Mrs => 'श्रीमती',
            self::Ms => 'सुश्री',
        };
    }
}
