<?php

namespace Modules\JarManagement\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\JarManagement\Enums\JarCondition;
use Modules\JarManagement\Enums\JarLotItemStatus;
use Modules\JarManagement\Enums\JarMovementEvent;
use Modules\JarManagement\Enums\JarPaymentMethod;
use Modules\JarManagement\Enums\JarReturnVerificationStatus;
use Modules\JarManagement\Enums\JarStatus;
use Modules\JarManagement\Enums\PaymentStatus;
use Modules\JarManagement\Enums\ReconciliationStatus;
use Modules\JarManagement\Models\Jar;
use Modules\JarManagement\Models\JarCustomer;
use Modules\JarManagement\Models\JarDelivery;
use Modules\JarManagement\Models\JarDeliveryItem;
use Modules\JarManagement\Models\JarDeliveryReturn;
use Modules\JarManagement\Models\JarLotItem;
use Modules\JarManagement\Models\JarVehicleLot;

/**
 * One customer stop = one JarDelivery: the filled jars handed over and the
 * empty jars collected back are recorded together, then reconciled against
 * the customer's running balance in the same transaction (see reconcile()).
 */
class DeliveryService
{
    public function __construct(
        private readonly JarMovementRecorder $movements,
        private readonly JarRegistrationService $registration,
    ) {}

    /**
     * @param  array<int, string>  $jarCodesDelivered
     * @param  array<int, array{jar_code?: string, is_new_registration?: bool, condition?: string, notes?: string}>  $returns
     */
    public function recordDelivery(
        JarVehicleLot $lot,
        JarCustomer $customer,
        User $staff,
        array $jarCodesDelivered,
        array $returns,
        ?float $unitPrice,
        float $amountPaid,
        ?JarPaymentMethod $paymentMethod,
        ?string $notes = null,
    ): JarDelivery {
        return DB::transaction(function () use (
            $lot, $customer, $staff, $jarCodesDelivered, $returns, $unitPrice, $amountPaid, $paymentMethod, $notes,
        ) {
            $delivery = JarDelivery::create([
                'jar_vehicle_lot_id' => $lot->id,
                'jar_customer_id' => $customer->id,
                'staff_id' => $staff->id,
                'delivered_at' => now(),
                'price_per_jar' => $unitPrice,
                'payment_method' => $paymentMethod,
                'notes' => $notes,
            ]);

            $deliveredCount = $this->deliverJars($delivery, $lot, $customer, $jarCodesDelivered, $unitPrice, $staff);
            $collectedCount = $this->collectReturns($delivery, $lot, $customer, $returns, $staff);

            $totalAmount = round(($unitPrice ?? 0) * $deliveredCount, 2);
            $paymentStatus = match (true) {
                $amountPaid <= 0 && $totalAmount > 0 => PaymentStatus::Unpaid,
                $amountPaid >= $totalAmount => PaymentStatus::Paid,
                default => PaymentStatus::Partial,
            };

            [$reconciliation, $outstanding, $excess] = $this->reconcile($customer, $deliveredCount, $collectedCount);

            $delivery->update([
                'filled_jars_delivered_count' => $deliveredCount,
                'empty_jars_collected_count' => $collectedCount,
                'total_amount' => $totalAmount,
                'amount_paid' => $amountPaid,
                'payment_status' => $paymentStatus,
                'reconciliation_status' => $reconciliation,
                'outstanding_jars' => $outstanding,
                'excess_jars' => $excess,
            ]);

            return $delivery->fresh(['items.jar', 'returns.jar', 'customer']);
        });
    }

    private function deliverJars(JarDelivery $delivery, JarVehicleLot $lot, JarCustomer $customer, array $jarCodes, ?float $unitPrice, User $staff): int
    {
        foreach ($jarCodes as $jarCode) {
            $jar = Jar::where('jar_code', $jarCode)->first();

            if (! $jar) {
                throw ValidationException::withMessages(['jar_codes' => "No jar is registered with code {$jarCode}."]);
            }

            $lotItem = JarLotItem::where('jar_vehicle_lot_id', $lot->id)->where('jar_id', $jar->id)->first();

            if (! $lotItem || $lotItem->status !== JarLotItemStatus::Loaded || $jar->status !== JarStatus::Dispatched) {
                throw ValidationException::withMessages([
                    'jar_codes' => "Jar {$jarCode} is not a filled jar currently out on this vehicle lot.",
                ]);
            }

            $lotItem->update(['status' => JarLotItemStatus::Delivered]);

            $jar->update(['status' => JarStatus::WithCustomer, 'current_customer_id' => $customer->id]);

            JarDeliveryItem::create([
                'jar_delivery_id' => $delivery->id,
                'jar_id' => $jar->id,
                'unit_price' => $unitPrice,
            ]);

            $this->movements->record(
                $jar, JarMovementEvent::Delivered, JarStatus::Dispatched, JarStatus::WithCustomer, $staff,
                ['jar_vehicle_lot_id' => $lot->id, 'jar_customer_id' => $customer->id, 'jar_delivery_id' => $delivery->id],
            );
        }

        return count($jarCodes);
    }

    private function collectReturns(JarDelivery $delivery, JarVehicleLot $lot, JarCustomer $customer, array $returns, User $staff): int
    {
        foreach ($returns as $return) {
            $isNew = (bool) ($return['is_new_registration'] ?? false);
            $condition = JarCondition::tryFrom($return['condition'] ?? 'good') ?? JarCondition::Good;

            if ($isNew) {
                $jar = $this->registration->register($staff, 'Registered in the field during empty-jar collection.');
            } else {
                $jarCode = $return['jar_code'] ?? null;
                $jar = $jarCode ? Jar::where('jar_code', $jarCode)->first() : null;

                if (! $jar) {
                    throw ValidationException::withMessages(['returns' => "No jar is registered with code {$jarCode}."]);
                }
            }

            // A jar with a traceable prior customer that doesn't match this
            // stop, or one with no traceable pedigree at all (brand new/
            // uncoded), can't be confirmed as genuinely this customer's —
            // flag it for a manager to verify rather than silently accept it.
            $priorCustomerId = $jar->current_customer_id;
            $verification = ($isNew || ($priorCustomerId && $priorCustomerId !== $customer->id))
                ? JarReturnVerificationStatus::Flagged
                : JarReturnVerificationStatus::Verified;

            $fromStatus = $jar->status;

            $jar->update([
                'status' => JarStatus::EmptyCollected,
                'current_vehicle_lot_id' => $lot->id,
                'current_customer_id' => null,
            ]);

            JarDeliveryReturn::create([
                'jar_delivery_id' => $delivery->id,
                'jar_id' => $jar->id,
                'is_new_registration' => $isNew,
                'condition' => $condition,
                'verification_status' => $verification,
                'notes' => $return['notes'] ?? null,
                'collected_by' => $staff->id,
                'collected_at' => now(),
            ]);

            $this->movements->record(
                $jar, JarMovementEvent::CollectedEmpty, $fromStatus, JarStatus::EmptyCollected, $staff,
                ['jar_vehicle_lot_id' => $lot->id, 'jar_customer_id' => $customer->id, 'jar_delivery_id' => $delivery->id],
            );
        }

        return count($returns);
    }

    /**
     * Nets today's shortfall/excess against whatever balance the customer
     * already carried, which is what "carry the balance to future
     * deliveries" means in practice: a customer who owed 2 and returns 2
     * extra today walks away even, not "owed 2 refunded plus 2 new excess".
     *
     * @return array{0: ReconciliationStatus, 1: int, 2: int} [status, outstanding, excess]
     */
    private function reconcile(JarCustomer $customer, int $delivered, int $collected): array
    {
        $locked = JarCustomer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

        $net = $locked->jars_outstanding + $delivered - $collected;

        if ($net >= 0) {
            $locked->update(['jars_outstanding' => $net]);

            return [$net === 0 ? ReconciliationStatus::Settled : ReconciliationStatus::Outstanding, $net, 0];
        }

        $locked->update(['jars_outstanding' => 0]);

        return [ReconciliationStatus::Excess, 0, -$net];
    }
}
