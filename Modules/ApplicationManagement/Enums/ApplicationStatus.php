<?php

namespace Modules\ApplicationManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;
use App\Enums\WorkflowStage;
use App\Workflow\Contracts\WorkflowStatus;

/**
 * Share application lifecycle. Only the review chain participates in the
 * workflow engine:
 *
 *   payment_verified → verified → reviewed → approved
 *
 * Finance verifies the payment first; the three sign-off stages follow. The
 * banking, allotment and refund statuses are lifecycle states driven by other
 * services, so they report no pending stage and the engine refuses to act on
 * them.
 */
enum ApplicationStatus: string implements HasLabels, WorkflowStatus
{
    use HasOptions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case SentToBank = 'sent_to_bank';
    case BankAccepted = 'bank_accepted';
    case Blocked = 'blocked';
    case PaymentPending = 'payment_pending';
    case PaymentVerified = 'payment_verified';
    case Verified = 'verified';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Returned = 'returned';
    case Allotted = 'allotted';
    case PartiallyAllotted = 'partially_allotted';
    case NotAllotted = 'not_allotted';
    case RefundInitiated = 'refund_initiated';
    case RefundCompleted = 'refund_completed';
    case DematCredited = 'demat_credited';

    public function labelEn(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::SentToBank => 'Sent to Bank',
            self::BankAccepted => 'Bank Accepted',
            self::Blocked => 'Blocked',
            self::PaymentPending => 'Payment Pending',
            self::PaymentVerified => 'Awaiting Verification',
            self::Verified => 'Awaiting Review',
            self::Reviewed => 'Awaiting Approval',
            self::Approved => 'Approved',
            self::Returned => 'Returned for Correction',
            self::Allotted => 'Allotted',
            self::PartiallyAllotted => 'Partially Allotted',
            self::NotAllotted => 'Not Allotted',
            self::RefundInitiated => 'Refund Initiated',
            self::RefundCompleted => 'Refund Completed',
            self::DematCredited => 'Demat Credited',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Draft => 'मस्यौदा',
            self::Submitted => 'पेश गरिएको',
            self::SentToBank => 'बैंकमा पठाइएको',
            self::BankAccepted => 'बैंकबाट स्वीकृत',
            self::Blocked => 'रोक्का',
            self::PaymentPending => 'भुक्तानी बाँकी',
            self::PaymentVerified => 'प्रमाणिकरण बाँकी',
            self::Verified => 'पुनरावलोकन बाँकी',
            self::Reviewed => 'स्वीकृति बाँकी',
            self::Approved => 'स्वीकृत',
            self::Returned => 'सुधारका लागि फिर्ता',
            self::Allotted => 'बाँडफाँट भएको',
            self::PartiallyAllotted => 'आंशिक बाँडफाँट',
            self::NotAllotted => 'बाँडफाँट नभएको',
            self::RefundInitiated => 'फिर्ता प्रक्रिया सुरु',
            self::RefundCompleted => 'फिर्ता सम्पन्न',
            self::DematCredited => 'डिम्याटमा जम्मा',
        };
    }

    public function pendingStage(): ?WorkflowStage
    {
        return match ($this) {
            self::PaymentVerified => WorkflowStage::Verifier,
            self::Verified => WorkflowStage::Reviewer,
            self::Reviewed => WorkflowStage::Approver,
            default => null,
        };
    }

    public function advance(): static
    {
        return match ($this) {
            self::PaymentVerified => self::Verified,
            self::Verified => self::Reviewed,
            self::Reviewed => self::Approved,
            default => $this,
        };
    }

    public function sendBackTarget(): ?static
    {
        return match ($this) {
            self::Verified => self::PaymentVerified,
            self::Reviewed => self::Verified,
            default => null,
        };
    }

    public static function returned(): static
    {
        return self::Returned;
    }

    public static function chainStart(): static
    {
        return self::PaymentVerified;
    }

    /**
     * Lifecycle ordering, used to reject backwards transitions. Note the
     * review chain runs verifier → reviewer, so Verified precedes Reviewed.
     * Returned and Rejected sit outside the ordering and are reachable from
     * anywhere in the chain.
     *
     * @return array<int, self>
     */
    public static function flow(): array
    {
        return [
            self::Draft,
            self::Submitted,
            self::SentToBank,
            self::BankAccepted,
            self::Blocked,
            self::PaymentPending,
            self::PaymentVerified,
            self::Verified,
            self::Reviewed,
            self::Approved,
            self::Allotted,
            self::PartiallyAllotted,
            self::NotAllotted,
            self::RefundInitiated,
            self::RefundCompleted,
            self::DematCredited,
        ];
    }

    /** Whether the lifecycle may move from this status to $target. */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target || $target === self::Returned) {
            return true;
        }

        $flow = self::flow();
        $from = array_search($this, $flow, true);
        $to = array_search($target, $flow, true);

        if ($from === false || $to === false) {
            return false;
        }

        return $to >= $from;
    }
}
