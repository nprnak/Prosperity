<?php

namespace Modules\JarManagement\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabels;

/**
 * A jar's current place in the closed loop:
 *
 *   Registered → InBatch → (QualityRejected | Available) → Dispatched →
 *   WithCustomer → EmptyCollected → (Accepted → back to InBatch | Quarantined) → Retired
 *
 * QualityRejected and Accepted jars both feed back into a future batch via
 * InBatch, which is what makes the loop closed rather than linear.
 */
enum JarStatus: string implements HasLabels
{
    use HasOptions;

    case Registered = 'registered';
    case InBatch = 'in_batch';
    case QualityRejected = 'quality_rejected';
    case Available = 'available';
    case Dispatched = 'dispatched';
    case WithCustomer = 'with_customer';
    case EmptyCollected = 'empty_collected';
    case Quarantined = 'quarantined';
    case Accepted = 'accepted';
    case Retired = 'retired';

    public function labelEn(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::InBatch => 'In Production Batch',
            self::QualityRejected => 'Quality Rejected',
            self::Available => 'Available for Dispatch',
            self::Dispatched => 'Dispatched',
            self::WithCustomer => 'With Customer',
            self::EmptyCollected => 'Empty (In Vehicle)',
            self::Quarantined => 'Quarantined',
            self::Accepted => 'Accepted (Cleaning Queue)',
            self::Retired => 'Retired',
        };
    }

    public function labelNp(): string
    {
        return match ($this) {
            self::Registered => 'दर्ता भएको',
            self::InBatch => 'उत्पादन ब्याचमा',
            self::QualityRejected => 'गुणस्तर अस्वीकृत',
            self::Available => 'ढुवानीका लागि तयार',
            self::Dispatched => 'पठाइएको',
            self::WithCustomer => 'ग्राहकसँग',
            self::EmptyCollected => 'खाली (सवारीमा)',
            self::Quarantined => 'क्वारेन्टाइनमा',
            self::Accepted => 'स्वीकृत (सफाइ लाइनमा)',
            self::Retired => 'निष्क्रिय',
        };
    }
}
