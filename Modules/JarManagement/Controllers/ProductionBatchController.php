<?php

namespace Modules\JarManagement\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\JarManagement\Enums\JarBatchQualityResult;
use Modules\JarManagement\Enums\ProductionBatchStatus;
use Modules\JarManagement\Models\JarProductionBatch;
use Modules\JarManagement\Repositories\JarProductionBatchRepository;
use Modules\JarManagement\Requests\RecordProductionStepRequest;
use Modules\JarManagement\Requests\ScanJarIntoBatchRequest;
use Modules\JarManagement\Requests\StoreProductionBatchRequest;
use Modules\JarManagement\Requests\SubmitQualityApprovalRequest;
use Modules\JarManagement\Services\ProductionBatchService;

class ProductionBatchController extends Controller
{
    public function __construct(
        private readonly JarProductionBatchRepository $batches,
        private readonly ProductionBatchService $service,
    ) {}

    public function index()
    {
        return Inertia::render('JarManagement/Production/Batches', [
            'batches' => $this->batches->paginateLatest(),
            'statusOptions' => ProductionBatchStatus::options(),
        ]);
    }

    public function store(StoreProductionBatchRequest $request)
    {
        $batch = $this->service->createBatch($request->user(), $request->date('production_date'));

        return redirect()->route('jar.production.show', $batch)->with('success', "Batch {$batch->batch_code} created.");
    }

    public function show(JarProductionBatch $batch)
    {
        $batch->load(['items.jar', 'items.scannedBy', 'creator', 'cleaningBy', 'refillingBy', 'sealingBy', 'qualityApprovedBy']);

        return Inertia::render('JarManagement/Production/BatchShow', [
            'batch' => $batch,
            'qualityResultOptions' => JarBatchQualityResult::options(),
        ]);
    }

    public function scanJar(ScanJarIntoBatchRequest $request, JarProductionBatch $batch)
    {
        $this->service->scanJarIntoBatch($batch, $request->string('jar_code')->trim()->upper()->toString(), $request->user());

        return back()->with('success', 'Jar added to batch.');
    }

    public function recordCleaning(RecordProductionStepRequest $request, JarProductionBatch $batch)
    {
        $this->service->recordCleaning($batch, $request->user(), $request->date('at'));

        return back()->with('success', 'Cleaning recorded.');
    }

    public function recordRefilling(RecordProductionStepRequest $request, JarProductionBatch $batch)
    {
        $this->service->recordRefilling($batch, $request->user(), $request->date('at'));

        return back()->with('success', 'Refilling recorded.');
    }

    public function recordSealing(RecordProductionStepRequest $request, JarProductionBatch $batch)
    {
        $this->service->recordSealing($batch, $request->user(), $request->date('at'));

        return back()->with('success', 'Sealing recorded — batch is ready for quality approval.');
    }

    public function submitQualityApproval(SubmitQualityApprovalRequest $request, JarProductionBatch $batch)
    {
        $this->service->submitQualityApproval(
            $batch,
            $request->user(),
            $request->input('approved_jar_ids', []),
            $request->input('rejections', []),
            $request->input('notes'),
        );

        return redirect()->route('jar.production.show', $batch)->with('success', 'Quality approval recorded.');
    }
}
