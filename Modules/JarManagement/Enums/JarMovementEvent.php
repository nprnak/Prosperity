<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

/**
 * One entry per row in the jar_movements ledger — the append-only trail that
 * answers "where is this jar, who had it, when". Every jar-state-changing
 * service call writes exactly one of these alongside updating jars.status.
 */
enum JarMovementEvent: string implements HasLabels
{
    use HasOptions;

    case Registered = 'registered';
    case AddedToBatch = 'added_to_batch';
    case QualityApproved = 'quality_approved';
    case QualityRejected = 'quality_rejected';
    case Loaded = 'loaded';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case CollectedEmpty = 'collected_empty';
    case FactoryAccepted = 'factory_accepted';
    case FactoryQuarantined = 'factory_quarantined';
    case QuarantineResolved = 'quarantine_resolved';
    case Retired = 'retired';

    public function labelEn(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::AddedToBatch => 'Added to Production Batch',
            self::QualityApproved => 'Quality Approved',
            self::QualityRejected => 'Quality Rejected',
            self::Loaded => 'Loaded onto Vehicle',
            self::Dispatched => 'Vehicle Dispatched',
            self::Delivered => 'Delivered to Customer',
            self::CollectedEmpty => 'Empty Collected from Customer',
            self::FactoryAccepted => 'Accepted at Factory',
            self::FactoryQuarantined => 'Quarantined at Factory',
            self::QuarantineResolved => 'Quarantine Resolved',
            self::Retired => 'Retired',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Registered => 'दर्ता गरियो',
            self::AddedToBatch => 'उत्पादन ब्याचमा थपियो',
            self::QualityApproved => 'गुणस्तर स्वीकृत भयो',
            self::QualityRejected => 'गुणस्तर अस्वीकृत भयो',
            self::Loaded => 'सवारीमा लोड गरियो',
            self::Dispatched => 'सवारी पठाइयो',
            self::Delivered => 'ग्राहकलाई डेलिभर गरियो',
            self::CollectedEmpty => 'ग्राहकबाट खाली संकलन गरियो',
            self::FactoryAccepted => 'कारखानामा स्वीकृत भयो',
            self::FactoryQuarantined => 'कारखानामा क्वारेन्टाइन गरियो',
            self::QuarantineResolved => 'क्वारेन्टाइन समाधान भयो',
            self::Retired => 'निष्क्रिय गरियो',
        };
    }
}
