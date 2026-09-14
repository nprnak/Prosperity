<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum JarBatchQualityResult: string implements HasLabels
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function labelEn(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Pending => 'बाँकी',
            self::Approved => 'स्वीकृत',
            self::Rejected => 'अस्वीकृत',
        };
    }
}
