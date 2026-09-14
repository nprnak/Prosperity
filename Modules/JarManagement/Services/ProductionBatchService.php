<?php

namespace Modules\JarManagement\Services;

use App\Models\User;
use App\Services\NumberGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\JarManagement\Enums\JarBatchQualityResult;
use Modules\JarManagement\Enums\JarMovementEvent;
use Modules\JarManagement\Enums\JarStatus;
use Modules\JarManagement\Enums\ProductionBatchStatus;
use Modules\JarManagement\Models\Jar;
use Modules\JarManagement\Models\JarBatchItem;
use Modules\JarManagement\Models\JarProductionBatch;

/**
 * Cleaning → Refilling → Sealing → Batch Assignment → Scan Jar → Quality
 * Approval, as laid out in the production flowchart. Each step is its own
 * method so the controller can call them independently as staff complete
 * them through the shift, rather than requiring one big form submit.
 */
class ProductionBatchService
{
    /** A jar may only enter a batch from one of these states. */
    private const ELIGIBLE_FOR_BATCH = [JarStatus::Registered, JarStatus::QualityRejected, JarStatus::Accepted];

    public function __construct(
        private readonly NumberGeneratorService $numbers,
        private readonly JarMovementRecorder $movements,
    ) {}

    public function createBatch(User $by, ?Carbon $productionDate = null): JarProductionBatch
    {
        return JarProductionBatch::create([
            'batch_code' => $this->numbers->generateJarBatchCode($productionDate),
            'production_date' => $productionDate ?: now()->toDateString(),
            'status' => ProductionBatchStatus::Open,
            'created_by' => $by->id,
        ]);
    }

    public function scanJarIntoBatch(JarProductionBatch $batch, string $jarCode, User $by): JarBatchItem
    {
        if ($batch->status !== ProductionBatchStatus::Open) {
            throw ValidationException::withMessages(['jar_code' => 'This batch is no longer open for scanning.']);
        }

        $jar = Jar::where('jar_code', $jarCode)->first();

        if (! $jar) {
            throw ValidationException::withMessages(['jar_code' => "No jar is registered with code {$jarCode}."]);
        }

        if (! in_array($jar->status, self::ELIGIBLE_FOR_BATCH, true)) {
            throw ValidationException::withMessages([
                'jar_code' => "Jar {$jarCode} is currently \"{$jar->status->labelEn()}\" and cannot be added to a production batch.",
            ]);
        }

        return DB::transaction(function () use ($batch, $jar, $by) {
            $item = JarBatchItem::create([
                'jar_production_batch_id' => $batch->id,
                'jar_id' => $jar->id,
                'quality_result' => JarBatchQualityResult::Pending,
                'scanned_by' => $by->id,
                'scanned_at' => now(),
            ]);

            $from = $jar->status;
            $jar->update(['status' => JarStatus::InBatch, 'current_batch_id' => $batch->id]);

            $this->movements->record(
                $jar, JarMovementEvent::AddedToBatch, $from, JarStatus::InBatch, $by,
                ['jar_production_batch_id' => $batch->id],
            );

            return $item;
        });
    }

    public function recordCleaning(JarProductionBatch $batch, User $by, ?Carbon $at = null): JarProductionBatch
    {
        $batch->update(['cleaning_at' => $at ?: now(), 'cleaning_by' => $by->id]);

        return $batch;
    }

    public function recordRefilling(JarProductionBatch $batch, User $by, ?Carbon $at = null): JarProductionBatch
    {
        $batch->update(['refilling_at' => $at ?: now(), 'refilling_by' => $by->id]);

        return $batch;
    }

    public function recordSealing(JarProductionBatch $batch, User $by, ?Carbon $at = null): JarProductionBatch
    {
        $batch->update([
            'sealing_at' => $at ?: now(),
            'sealing_by' => $by->id,
            'status' => ProductionBatchStatus::PendingApproval,
        ]);

        return $batch;
    }

    /**
     * @param  array<int>  $approvedJarIds  jar IDs (not batch-item IDs) that passed QC
     * @param  array<int, array{jar_id: int, reason: string}>  $rejections
     */
    public function submitQualityApproval(
        JarProductionBatch $batch,
        User $by,
        array $approvedJarIds,
        array $rejections,
        ?string $notes = null,
    ): JarProductionBatch {
        return DB::transaction(function () use ($batch, $by, $approvedJarIds, $rejections, $notes) {
            $rejectedById = collect($rejections)->keyBy('jar_id');

            $batch->items()->with('jar')->get()->each(function (JarBatchItem $item) use ($approvedJarIds, $rejectedById, $by) {
                $jar = $item->jar;
                $from = $jar->status;

                if ($rejectedById->has($jar->id)) {
                    $item->update([
                        'quality_result' => JarBatchQualityResult::Rejected,
                        'rejection_reason' => $rejectedById->get($jar->id)['reason'] ?? null,
                    ]);
                    $jar->update(['status' => JarStatus::QualityRejected]);
                    $this->movements->record(
                        $jar, JarMovementEvent::QualityRejected, $from, JarStatus::QualityRejected, $by,
                        ['jar_production_batch_id' => $item->jar_production_batch_id],
                        $rejectedById->get($jar->id)['reason'] ?? null,
                    );
                } elseif (in_array($jar->id, $approvedJarIds, true)) {
                    $item->update(['quality_result' => JarBatchQualityResult::Approved]);
                    $jar->update(['status' => JarStatus::Available]);
                    $this->movements->record(
                        $jar, JarMovementEvent::QualityApproved, $from, JarStatus::Available, $by,
                        ['jar_production_batch_id' => $item->jar_production_batch_id],
                    );
                }
            });

            $batch->update([
                'status' => ProductionBatchStatus::Completed,
                'quality_approved_by' => $by->id,
                'quality_approved_at' => now(),
                'quality_notes' => $notes,
            ]);

            return $batch;
        });
    }
}
