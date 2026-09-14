<?php

namespace Modules\JarManagement\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\JarManagement\Enums\JarMovementEvent;
use Modules\JarManagement\Enums\JarReceiptDecision;
use Modules\JarManagement\Enums\JarStatus;
use Modules\JarManagement\Enums\QuarantineReason;
use Modules\JarManagement\Enums\VehicleLotStatus;
use Modules\JarManagement\Models\Jar;
use Modules\JarManagement\Models\JarFactoryReceipt;
use Modules\JarManagement\Models\JarFactoryReceiptItem;
use Modules\JarManagement\Models\JarVehicleLot;

/**
 * Vehicle returns → production team scans empties → match against the
 * vehicle's own recorded returns → accept (cleaning queue) or quarantine.
 */
class FactoryReceiptService
{
    public function __construct(private readonly JarMovementRecorder $movements) {}

    /** Opens (or resumes) the one receipt a lot gets for its return trip. */
    public function openReceipt(JarVehicleLot $lot, User $by): JarFactoryReceipt
    {
        if ($lot->status !== VehicleLotStatus::Dispatched) {
            throw ValidationException::withMessages(['lot' => 'This lot is not out on dispatch.']);
        }

        return DB::transaction(function () use ($lot, $by) {
            $receipt = JarFactoryReceipt::firstOrCreate(
                ['jar_vehicle_lot_id' => $lot->id],
                ['received_by' => $by->id, 'received_at' => now()],
            );

            if ($receipt->wasRecentlyCreated) {
                $expected = Jar::where('current_vehicle_lot_id', $lot->id)
                    ->where('status', JarStatus::EmptyCollected)
                    ->count();

                $receipt->update(['total_expected' => $expected]);
                $lot->update(['status' => VehicleLotStatus::Returned, 'returned_at' => now()]);
            }

            return $receipt->fresh();
        });
    }

    public function scanReturn(
        JarFactoryReceipt $receipt,
        string $jarCode,
        User $by,
        JarReceiptDecision $decision,
        ?QuarantineReason $reason = null,
        ?string $notes = null,
    ): JarFactoryReceiptItem {
        return DB::transaction(function () use ($receipt, $jarCode, $by, $decision, $reason, $notes) {
            $jar = Jar::where('jar_code', $jarCode)->first();

            if (! $jar) {
                throw ValidationException::withMessages(['jar_code' => "Unknown jar code {$jarCode} — verify it before recording a decision."]);
            }

            if (JarFactoryReceiptItem::where('jar_factory_receipt_id', $receipt->id)->where('jar_id', $jar->id)->exists()) {
                throw ValidationException::withMessages(['jar_code' => "Jar {$jarCode} has already been scanned on this receipt."]);
            }

            $matched = $jar->current_vehicle_lot_id === $receipt->jar_vehicle_lot_id && $jar->status === JarStatus::EmptyCollected;

            if ($decision === JarReceiptDecision::Accepted && ! $matched) {
                // An unmatched jar can only be accepted once it's confirmed it
                // really did travel on this lot — force quarantine instead so
                // a mismatch never slips straight into the cleaning queue.
                throw ValidationException::withMessages([
                    'jar_code' => "Jar {$jarCode} does not match this vehicle's recorded returns — quarantine it for verification instead.",
                ]);
            }

            $item = JarFactoryReceiptItem::create([
                'jar_factory_receipt_id' => $receipt->id,
                'jar_id' => $jar->id,
                'decision' => $decision,
                'quarantine_reason' => $decision === JarReceiptDecision::Quarantined ? $reason : null,
                'matched_to_vehicle' => $matched,
                'scanned_by' => $by->id,
                'scanned_at' => now(),
            ]);

            $from = $jar->status;

            if ($decision === JarReceiptDecision::Accepted) {
                $jar->update(['status' => JarStatus::Accepted, 'current_vehicle_lot_id' => null]);
                $this->movements->record(
                    $jar, JarMovementEvent::FactoryAccepted, $from, JarStatus::Accepted, $by,
                    ['jar_factory_receipt_id' => $receipt->id],
                );
            } else {
                $jar->update(['status' => JarStatus::Quarantined, 'current_vehicle_lot_id' => null]);
                $this->movements->record(
                    $jar, JarMovementEvent::FactoryQuarantined, $from, JarStatus::Quarantined, $by,
                    ['jar_factory_receipt_id' => $receipt->id],
                    $reason?->labelEn().($notes ? " — {$notes}" : ''),
                );
            }

            $receipt->increment('total_scanned');
            $receipt->increment($decision === JarReceiptDecision::Accepted ? 'total_accepted' : 'total_quarantined');

            return $item;
        });
    }

    public function closeReceipt(JarFactoryReceipt $receipt): JarFactoryReceipt
    {
        $receipt->lot->update(['status' => VehicleLotStatus::Reconciled]);

        return $receipt;
    }

    /**
     * A quarantined jar isn't retired automatically — a manager decides
     * whether it can rejoin the cleaning queue or has to be written off.
     */
    public function resolveQuarantine(Jar $jar, User $by, bool $releaseToCleaningQueue, ?string $notes = null): Jar
    {
        if ($jar->status !== JarStatus::Quarantined) {
            throw ValidationException::withMessages(['jar' => 'This jar is not currently quarantined.']);
        }

        $to = $releaseToCleaningQueue ? JarStatus::Accepted : JarStatus::Retired;

        $jar->update(['status' => $to]);

        $this->movements->record($jar, JarMovementEvent::QuarantineResolved, JarStatus::Quarantined, $to, $by, notes: $notes);

        return $jar;
    }
}
