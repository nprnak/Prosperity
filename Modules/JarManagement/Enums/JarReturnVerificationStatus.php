<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum JarReturnVerificationStatus: string implements HasLabels
{
    use HasOptions;

    case Verified = 'verified';
    case Flagged = 'flagged';

    public function labelEn(): string
    {
        return match ($this) {
            self::Verified => 'Verified',
            self::Flagged => 'Flagged for Review',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Verified => 'प्रमाणित',
            self::Flagged => 'समीक्षाका लागि चिन्हित',
        };
    }
}
