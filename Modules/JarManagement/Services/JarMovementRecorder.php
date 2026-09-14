<?php

namespace Modules\JarManagement\Services;

use App\Models\User;
use Modules\JarManagement\Enums\JarMovementEvent;
use Modules\JarManagement\Enums\JarStatus;
use Modules\JarManagement\Models\Jar;
use Modules\JarManagement\Models\JarMovement;

/**
 * Every jar-state-changing service writes through here so the jar_movements
 * ledger — the source of truth for "where has this jar been" — never gets
 * out of sync with an ad-hoc ->save() elsewhere.
 */
class JarMovementRecorder
{
    /**
     * @param  array<string, int|null>  $refs  one of jar_production_batch_id,
     *         jar_vehicle_lot_id, jar_customer_id, jar_delivery_id, jar_factory_receipt_id
     */
    public function record(
        Jar $jar,
        JarMovementEvent $event,
        ?JarStatus $from,
        ?JarStatus $to,
        User $by,
        array $refs = [],
        ?string $notes = null,
    ): JarMovement {
        return JarMovement::create(array_merge([
            'jar_id' => $jar->id,
            'event_type' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'recorded_by' => $by->id,
            'occurred_at' => now(),
            'notes' => $notes,
        ], $refs));
    }
}
