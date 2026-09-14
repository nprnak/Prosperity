<?php

namespace Modules\JarManagement\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\JarManagement\Enums\JarReceiptDecision;
use Modules\JarManagement\Enums\QuarantineReason;
use Modules\JarManagement\Enums\VehicleLotStatus;
use Modules\JarManagement\Models\JarFactoryReceipt;
use Modules\JarManagement\Models\JarVehicleLot;
use Modules\JarManagement\Repositories\JarFactoryReceiptRepository;
use Modules\JarManagement\Requests\FactoryReceiptScanRequest;
use Modules\JarManagement\Services\FactoryReceiptService;

class FactoryReceiptController extends Controller
{
    public function __construct(
        private readonly JarFactoryReceiptRepository $receipts,
        private readonly FactoryReceiptService $service,
    ) {}

    /** Lots dispatched (still on the road) or already back and awaiting a receipt. */
    public function index()
    {
        return Inertia::render('JarManagement/Returns/FactoryReceipt', [
            'pendingLots' => JarVehicleLot::whereIn('status', [VehicleLotStatus::Dispatched, VehicleLotStatus::Returned])
                ->with(['vehicle', 'driver', 'assignedStaff', 'factoryReceipt'])
                ->withCount('items')
                ->orderByDesc('id')
                ->get(),
            'recentReceipts' => $this->receipts->paginateLatest(),
            'quarantineReasonOptions' => QuarantineReason::options(),
        ]);
    }

    public function open(JarVehicleLot $lot)
    {
        $receipt = $this->service->openReceipt($lot, request()->user());

        return redirect()->route('jar.receipts.show', $receipt);
    }

    public function show(JarFactoryReceipt $receipt)
    {
        $receipt->load(['lot.vehicle', 'lot.driver', 'items.jar', 'items.scannedBy']);

        return Inertia::render('JarManagement/Returns/ReceiptShow', [
            'receipt' => $receipt,
            'decisionOptions' => JarReceiptDecision::options(),
            'quarantineReasonOptions' => QuarantineReason::options(),
        ]);
    }

    public function scan(FactoryReceiptScanRequest $request, JarFactoryReceipt $receipt)
    {
        $this->service->scanReturn(
            $receipt,
            $request->string('jar_code')->trim()->upper()->toString(),
            $request->user(),
            JarReceiptDecision::from($request->input('decision')),
            $request->filled('quarantine_reason') ? QuarantineReason::from($request->input('quarantine_reason')) : null,
            $request->input('notes'),
        );

        return back()->with('success', 'Jar recorded.');
    }

    public function close(JarFactoryReceipt $receipt)
    {
        $this->service->closeReceipt($receipt);

        return redirect()->route('jar.receipts.index')->with('success', 'Receipt closed — lot reconciled.');
    }
}
