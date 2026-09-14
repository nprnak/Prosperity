<?php

namespace Tests\Feature\JarManagement;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\JarManagement\Enums\JarPaymentMethod;
use Modules\JarManagement\Enums\JarReceiptDecision;
use Modules\JarManagement\Enums\JarStatus;
use Modules\JarManagement\Enums\QuarantineReason;
use Modules\JarManagement\Models\JarCustomer;
use Modules\JarManagement\Models\JarDriver;
use Modules\JarManagement\Models\JarVehicle;
use Modules\JarManagement\Services\DeliveryService;
use Modules\JarManagement\Services\DispatchService;
use Modules\JarManagement\Services\FactoryReceiptService;
use Modules\JarManagement\Services\JarRegistrationService;
use Modules\JarManagement\Services\ProductionBatchService;
use Tests\TestCase;

class JarLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_field_staff_can_reach_deliveries_but_not_production(): void
    {
        $staff = $this->userWithRole('jar_field_staff');

        $this->actingAs($staff)->get('/jar/delivery')->assertOk();
        $this->actingAs($staff)->get('/jar/production')->assertForbidden();
    }

    public function test_production_staff_can_reach_production_but_not_customers(): void
    {
        $staff = $this->userWithRole('jar_production_staff');

        $this->actingAs($staff)->get('/jar/production')->assertOk();
        $this->actingAs($staff)->get('/jar/customers')->assertForbidden();
    }

    public function test_management_role_is_read_only(): void
    {
        $manager = $this->userWithRole('jar_management');

        $this->actingAs($manager)->get('/jar/dashboard')->assertOk();
        // No *.manage permission at all, so every mutating area is closed off.
        $this->actingAs($manager)->get('/jar/production')->assertForbidden();
        $this->actingAs($manager)->get('/jar/customers')->assertForbidden();
        $this->actingAs($manager)->get('/jar/delivery')->assertForbidden();
    }

    /**
     * The full closed loop: register → batch → quality-approve → lot → load
     * → dispatch → deliver → collect the exact number back → factory-accept
     * → the jar is available for a brand new batch again.
     */
    public function test_full_jar_lifecycle_closes_the_loop(): void
    {
        $factoryStaff = $this->userWithRole('jar_production_staff');
        $dispatchManager = $this->userWithRole('jar_dispatch_manager');
        $fieldStaff = $this->userWithRole('jar_field_staff');

        $registration = app(JarRegistrationService::class);
        $production = app(ProductionBatchService::class);
        $dispatch = app(DispatchService::class);
        $delivery = app(DeliveryService::class);
        $receipts = app(FactoryReceiptService::class);

        $jar = $registration->register($factoryStaff);
        $this->assertSame(JarStatus::Registered, $jar->status);

        $batch = $production->createBatch($factoryStaff);
        $production->scanJarIntoBatch($batch, $jar->jar_code, $factoryStaff);
        $this->assertSame(JarStatus::InBatch, $jar->fresh()->status);

        $production->recordCleaning($batch, $factoryStaff);
        $production->recordRefilling($batch, $factoryStaff);
        $production->recordSealing($batch, $factoryStaff);
        $production->submitQualityApproval($batch, $factoryStaff, [$jar->id], []);
        $this->assertSame(JarStatus::Available, $jar->fresh()->status);

        $vehicle = JarVehicle::create(['vehicle_number' => 'BA-1-KHA-1001', 'max_jars' => 80]);
        $lot = $dispatch->createLot($dispatchManager, [
            'jar_vehicle_id' => $vehicle->id,
            'assigned_staff_id' => $fieldStaff->id,
        ]);
        $dispatch->loadJar($lot, $jar->jar_code, $dispatchManager);
        $lot = $dispatch->dispatch($lot, $dispatchManager);
        $this->assertSame(JarStatus::Dispatched, $jar->fresh()->status);

        $customer = JarCustomer::create(['name' => 'Test Household', 'type' => 'household']);
        $jarDelivery = $delivery->recordDelivery(
            lot: $lot,
            customer: $customer,
            staff: $fieldStaff,
            jarCodesDelivered: [$jar->jar_code],
            returns: [['jar_code' => $jar->jar_code, 'condition' => 'good']],
            unitPrice: 50,
            amountPaid: 50,
            paymentMethod: JarPaymentMethod::Cash,
        );

        // Delivered and collected back the same jar in the same stop — a
        // customer's very first delivery this settles at zero either way.
        $this->assertEquals(0, $customer->fresh()->jars_outstanding);
        $this->assertSame('settled', $jarDelivery->reconciliation_status->value);
        $this->assertSame(JarStatus::EmptyCollected, $jar->fresh()->status);

        $receipt = $receipts->openReceipt($lot->fresh(), $factoryStaff);
        $this->assertEquals(1, $receipt->total_expected);

        $receipts->scanReturn($receipt, $jar->jar_code, $factoryStaff, JarReceiptDecision::Accepted);
        $jar->refresh();
        $this->assertSame(JarStatus::Accepted, $jar->status);

        // The loop closes: an accepted jar can go straight into a new batch.
        $nextBatch = $production->createBatch($factoryStaff);
        $production->scanJarIntoBatch($nextBatch, $jar->jar_code, $factoryStaff);
        $this->assertSame(JarStatus::InBatch, $jar->fresh()->status);

        $this->assertGreaterThanOrEqual(6, $jar->movements()->count());
    }

    public function test_loading_more_than_the_lot_cap_is_rejected(): void
    {
        $factoryStaff = $this->userWithRole('jar_production_staff');
        $dispatchManager = $this->userWithRole('jar_dispatch_manager');
        $fieldStaff = $this->userWithRole('jar_field_staff');

        $registration = app(JarRegistrationService::class);
        $production = app(ProductionBatchService::class);
        $dispatch = app(DispatchService::class);

        $vehicle = JarVehicle::create(['vehicle_number' => 'BA-1-KHA-1002', 'max_jars' => 2]);
        $lot = $dispatch->createLot($dispatchManager, [
            'jar_vehicle_id' => $vehicle->id,
            'assigned_staff_id' => $fieldStaff->id,
            'max_jars' => 2,
        ]);

        $jars = $registration->registerMany($factoryStaff, 3);
        $batch = $production->createBatch($factoryStaff);
        foreach ($jars as $jar) {
            $production->scanJarIntoBatch($batch, $jar->jar_code, $factoryStaff);
        }
        $production->submitQualityApproval($batch, $factoryStaff, $jars->pluck('id')->all(), []);

        $dispatch->loadJar($lot, $jars[0]->jar_code, $dispatchManager);
        $dispatch->loadJar($lot, $jars[1]->jar_code, $dispatchManager);

        $this->expectException(ValidationException::class);
        $dispatch->loadJar($lot, $jars[2]->jar_code, $dispatchManager);
    }

    public function test_reconciliation_outstanding_and_excess_cases(): void
    {
        $factoryStaff = $this->userWithRole('jar_production_staff');
        $dispatchManager = $this->userWithRole('jar_dispatch_manager');
        $fieldStaff = $this->userWithRole('jar_field_staff');

        $registration = app(JarRegistrationService::class);
        $production = app(ProductionBatchService::class);
        $dispatch = app(DispatchService::class);
        $delivery = app(DeliveryService::class);

        $vehicle = JarVehicle::create(['vehicle_number' => 'BA-1-KHA-1003', 'max_jars' => 80]);
        $lot = $dispatch->createLot($dispatchManager, ['jar_vehicle_id' => $vehicle->id, 'assigned_staff_id' => $fieldStaff->id]);
        $customer = JarCustomer::create(['name' => 'Case Customer', 'type' => 'household']);

        $jars = $registration->registerMany($factoryStaff, 5);
        $batch = $production->createBatch($factoryStaff);
        foreach ($jars as $jar) {
            $production->scanJarIntoBatch($batch, $jar->jar_code, $factoryStaff);
        }
        $production->submitQualityApproval($batch, $factoryStaff, $jars->pluck('id')->all(), []);
        foreach ($jars as $jar) {
            $dispatch->loadJar($lot, $jar->jar_code, $dispatchManager);
        }
        $lot = $dispatch->dispatch($lot, $dispatchManager);

        // Case 2: delivered 5, only 4 returned — 1 outstanding.
        $shortDelivery = $delivery->recordDelivery(
            lot: $lot, customer: $customer, staff: $fieldStaff,
            jarCodesDelivered: $jars->pluck('jar_code')->all(),
            returns: $jars->take(4)->map(fn ($j) => ['jar_code' => $j->jar_code, 'condition' => 'good'])->all(),
            unitPrice: 50, amountPaid: 250, paymentMethod: JarPaymentMethod::Cash,
        );
        $this->assertSame('outstanding', $shortDelivery->reconciliation_status->value);
        $this->assertEquals(1, $customer->fresh()->jars_outstanding);

        // The 5th jar is still with this same customer; collecting it back
        // along with two more jars already tied to them nets the 1 owed
        // jar and leaves exactly 2 as excess (Case 3).
        $extraJars = $registration->registerMany($factoryStaff, 2);
        foreach ($extraJars as $j) {
            $j->update(['status' => JarStatus::WithCustomer, 'current_customer_id' => $customer->id]);
        }

        $excessDelivery = $delivery->recordDelivery(
            lot: $lot, customer: $customer, staff: $fieldStaff,
            jarCodesDelivered: [],
            returns: [
                ['jar_code' => $jars[4]->jar_code, 'condition' => 'good'],
                ['jar_code' => $extraJars[0]->jar_code, 'condition' => 'good'],
                ['jar_code' => $extraJars[1]->jar_code, 'condition' => 'good'],
            ],
            unitPrice: null, amountPaid: 0, paymentMethod: null,
        );

        $this->assertSame('excess', $excessDelivery->reconciliation_status->value);
        $this->assertEquals(0, $customer->fresh()->jars_outstanding);
        $this->assertEquals(2, $excessDelivery->excess_jars);
    }

    public function test_quarantine_keeps_a_mismatched_jar_out_of_the_cleaning_queue(): void
    {
        $factoryStaff = $this->userWithRole('jar_production_staff');
        $registration = app(JarRegistrationService::class);
        $receipts = app(FactoryReceiptService::class);

        // A jar that was never dispatched on any lot has nothing to match
        // against — scanning it in as "accepted" must be refused.
        $jar = $registration->register($factoryStaff);

        $vehicle = JarVehicle::create(['vehicle_number' => 'BA-1-KHA-1004', 'max_jars' => 80]);
        $lot = \Modules\JarManagement\Models\JarVehicleLot::create([
            'lot_code' => 'LOT-TEST-1',
            'jar_vehicle_id' => $vehicle->id,
            'assigned_staff_id' => $factoryStaff->id,
            'status' => 'dispatched',
            'dispatched_at' => now(),
        ]);
        $receipt = $receipts->openReceipt($lot, $factoryStaff);

        $this->expectException(ValidationException::class);
        $receipts->scanReturn($receipt, $jar->jar_code, $factoryStaff, JarReceiptDecision::Accepted);
    }
}
