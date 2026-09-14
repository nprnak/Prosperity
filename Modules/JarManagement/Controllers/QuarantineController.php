<?php

namespace Modules\JarManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\JarManagement\Enums\JarReturnVerificationStatus;
use Modules\JarManagement\Enums\JarStatus;
use Modules\JarManagement\Models\Jar;
use Modules\JarManagement\Models\JarDeliveryReturn;
use Modules\JarManagement\Repositories\JarDeliveryRepository;
use Modules\JarManagement\Requests\ResolveQuarantineRequest;
use Modules\JarManagement\Services\FactoryReceiptService;

/** Review queue: quarantined jars and excess returns flagged for ownership verification. */
class QuarantineController extends Controller
{
    public function __construct(
        private readonly JarDeliveryRepository $deliveries,
        private readonly FactoryReceiptService $service,
    ) {}

    public function index()
    {
        return Inertia::render('JarManagement/Quarantine/Index', [
            'quarantinedJars' => Jar::where('status', JarStatus::Quarantined)
                ->with(['movements' => fn ($q) => $q->latest('occurred_at')->limit(1)])
                ->orderByDesc('id')
                ->paginate(20),
            'flaggedReturns' => $this->deliveries->flaggedReturns(),
        ]);
    }

    public function resolve(ResolveQuarantineRequest $request, Jar $jar)
    {
        $this->service->resolveQuarantine(
            $jar,
            $request->user(),
            $request->boolean('release_to_cleaning_queue'),
            $request->input('notes'),
        );

        return back()->with('success', "Jar {$jar->jar_code} ".($request->boolean('release_to_cleaning_queue') ? 'released to the cleaning queue.' : 'retired.'));
    }

    /** Clears a flagged excess-return once a manager has confirmed the jar's ownership/source. */
    public function verifyReturn(Request $request, JarDeliveryReturn $return)
    {
        $this->authorizeManage($request);

        $return->update(['verification_status' => JarReturnVerificationStatus::Verified]);

        return back()->with('success', 'Return marked as verified.');
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('jar.inventory.manage') || $request->user()->can('jar.dispatch.manage'), 403);
    }
}
