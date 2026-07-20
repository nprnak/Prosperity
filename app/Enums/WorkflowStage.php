<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

/**
 * The three sign-off stages, in order. Both the KYC profile chain and the
 * share application chain use the same sequence.
 */
enum WorkflowStage: string implements HasLabels
{
    use HasOptions;

    case Verifier = 'verifier';
    case Reviewer = 'reviewer';
    case Approver = 'approver';

    public function labelEn(): string
    {
        return match ($this) {
            self::Verifier => 'Verifier',
            self::Reviewer => 'Reviewer',
            self::Approver => 'Approver',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Verifier => 'प्रमाणिकरण गर्ने',
            self::Reviewer => 'पुनरावलोकन गर्ने',
            self::Approver => 'स्वीकृत गर्ने',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Verifier => self::Reviewer,
            self::Reviewer => self::Approver,
            self::Approver => null,
        };
    }

    public function previous(): ?self
    {
        return match ($this) {
            self::Verifier => null,
            self::Reviewer => self::Verifier,
            self::Approver => self::Reviewer,
        };
    }

    /**
     * Spatie permission name for this stage over a given subject,
     * e.g. 'profile.verify' or 'application.approve'.
     */
    public function permission(string $subject): string
    {
        $verb = match ($this) {
            self::Verifier => 'verify',
            self::Reviewer => 'review',
            self::Approver => 'approve',
        };

        return "{$subject}.{$verb}";
    }
}
