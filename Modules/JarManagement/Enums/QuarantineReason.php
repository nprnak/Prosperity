<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

enum QuarantineReason: string implements HasLabels
{
    use HasOptions;

    case Damaged = 'damaged';
    case InvalidCode = 'invalid_code';
    case UnknownJar = 'unknown_jar';
    case OwnershipIssue = 'ownership_issue';
    case QualityProblem = 'quality_problem';
    case Mismatch = 'mismatch';
    case Other = 'other';

    public function labelEn(): string
    {
        return match ($this) {
            self::Damaged => 'Damaged Jar',
            self::InvalidCode => 'Invalid Jar Code',
            self::UnknownJar => 'Unknown Jar',
            self::OwnershipIssue => 'Ownership/Source Issue',
            self::QualityProblem => 'Quality Problem',
            self::Mismatch => 'Mismatch with Vehicle Return',
            self::Other => 'Other',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Damaged => 'बिग्रिएको जार',
            self::InvalidCode => 'अमान्य जार कोड',
            self::UnknownJar => 'अज्ञात जार',
            self::OwnershipIssue => 'स्वामित्व/स्रोत समस्या',
            self::QualityProblem => 'गुणस्तर समस्या',
            self::Mismatch => 'सवारी फिर्तीसँग नमिलेको',
            self::Other => 'अन्य',
        };
    }
}
