<?php

namespace Modules\JarManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Modules\JarManagement\Enums\JarPaymentMethod;
use Modules\JarManagement\Models\JarCustomer;
use Modules\JarManagement\Models\JarVehicleLot;
use Modules\JarManagement\Repositories\JarDeliveryRepository;
use Modules\JarManagement\Repositories\JarVehicleLotRepository;
use Modules\JarManagement\Requests\RecordDeliveryRequest;
use Modules\JarManagement\Services\DeliveryService;

class DeliveryController extends Controller
{
    public function __construct(
        private readonly JarVehicleLotRepository $lots,
        private readonly JarDeliveryRepository $deliveries,
        private readonly DeliveryService $service,
    ) {}

    /** The field-staff landing page: their own active lots and today's deliveries. */
    public function myLots()
    {
        $user = request()->user();

        return Inertia::render('JarManagement/Delivery/MyLot', [
            'lots' => $this->lots->activeForStaff($user->id),
            'recentDeliveries' => $this->deliveries->forStaff($user->id, 10),
        ]);
    }

    public function store(RecordDeliveryRequest $request, JarVehicleLot $lot)
    {
        Gate::authorize('act', $lot);

        $customer = $request->filled('jar_customer_id')
            ? JarCustomer::findOrFail($request->integer('jar_customer_id'))
            : JarCustomer::create([...$request->input('new_customer', []), 'created_by' => $request->user()->id]);

        $delivery = $this->service->recordDelivery(
            lot: $lot,
            customer: $customer,
            staff: $request->user(),
            jarCodesDelivered: array_map(fn ($c) => strtoupper(trim($c)), $request->input('jar_codes', [])),
            returns: $request->input('returns', []),
            unitPrice: $request->input('unit_price') !== null ? (float) $request->input('unit_price') : $customer->default_price,
            amountPaid: (float) $request->input('amount_paid', 0),
            paymentMethod: $request->filled('payment_method') ? JarPaymentMethod::from($request->input('payment_method')) : null,
            notes: $request->input('notes'),
        );

        return back()->with('success', "Delivery recorded for {$customer->name}.")->with('lastDelivery', $delivery);
    }
}
