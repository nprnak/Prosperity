<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

/**
 * What a stage can do with a record. Every action requires remarks.
 *
 * There is no terminal rejection: a record a stage is unhappy with either goes
 * back to the applicant to correct (ReturnToApplicant) or back one stage for
 * another look (SendBack). Nothing is ever a permanent dead end.
 */
enum WorkflowAction: string implements HasLabels
{
    use HasOptions;

    case Approve = 'approve';
    case ReturnToApplicant = 'return_to_applicant';
    case SendBack = 'send_back';

    public function labelEn(): string
    {
        return match ($this) {
            self::Approve => 'Approve',
            self::ReturnToApplicant => 'Return to Applicant',
            self::SendBack => 'Send Back a Stage',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Approve => 'स्वीकृत',
            self::ReturnToApplicant => 'आवेदकलाई फिर्ता',
            self::SendBack => 'अघिल्लो चरणमा फिर्ता',
        };
    }

    /** Whether the record leaves staff hands and goes back to the applicant. */
    public function returnsToApplicant(): bool
    {
        return $this === self::ReturnToApplicant;
    }
}
