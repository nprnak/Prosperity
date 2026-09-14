<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum ProductionBatchStatus: string implements HasLabels
{
    use HasOptions;

    case Open = 'open';
    case PendingApproval = 'pending_approval';
    case Completed = 'completed';

    public function labelEn(): string
    {
        return match ($this) {
            self::Open => 'Open (Processing)',
            self::PendingApproval => 'Pending Quality Approval',
            self::Completed => 'Completed',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Open => 'खुला (प्रशोधनमा)',
            self::PendingApproval => 'गुणस्तर स्वीकृति बाँकी',
            self::Completed => 'सम्पन्न',
        };
    }
}
