<?php

namespace Modules\ApplicantManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;
use App\Enums\WorkflowStage;
use App\Workflow\Contracts\WorkflowStatus;

/**
 * KYC profile chain: incomplete → submitted → verified → reviewed → approved.
 *
 * Each in-chain status names the sign-off already given, so the stage still
 * owed is the one after it.
 */
enum ProfileStatus: string implements HasLabels, WorkflowStatus
{
    use HasOptions;

    case Incomplete = 'incomplete';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Returned = 'returned';

    public function labelEn(): string
    {
        return match ($this) {
            self::Incomplete => 'Incomplete',
            self::Submitted => 'Awaiting Verification',
            self::Verified => 'Awaiting Review',
            self::Reviewed => 'Awaiting Approval',
            self::Approved => 'Approved',
            self::Returned => 'Returned for Correction',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Incomplete => 'अपूर्ण',
            self::Submitted => 'प्रमाणिकरण बाँकी',
            self::Verified => 'पुनरावलोकन बाँकी',
            self::Reviewed => 'स्वीकृति बाँकी',
            self::Approved => 'स्वीकृत',
            self::Returned => 'सुधारका लागि फिर्ता',
        };
    }

    public function pendingStage(): ?WorkflowStage
    {
        return match ($this) {
            self::Submitted => WorkflowStage::Verifier,
            self::Verified => WorkflowStage::Reviewer,
            self::Reviewed => WorkflowStage::Approver,
            default => null,
        };
    }

    public function advance(): static
    {
        return match ($this) {
            self::Submitted => self::Verified,
            self::Verified => self::Reviewed,
            self::Reviewed => self::Approved,
            default => $this,
        };
    }

    public function sendBackTarget(): ?static
    {
        return match ($this) {
            // Verified is awaiting the reviewer; sending back returns it to the verifier.
            self::Verified => self::Submitted,
            self::Reviewed => self::Verified,
            // Submitted sits with the first stage — there is nothing behind it.
            default => null,
        };
    }

    public static function returned(): static
    {
        return self::Returned;
    }

    public static function chainStart(): static
    {
        return self::Submitted;
    }

    /** Statuses from which the applicant may edit and resubmit. */
    public function isEditableByApplicant(): bool
    {
        return in_array($this, [self::Incomplete, self::Returned], true);
    }
}
