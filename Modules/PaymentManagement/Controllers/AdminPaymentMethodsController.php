<?php

namespace Modules\PaymentManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Modules\PaymentManagement\Models\PaymentMethod;
use Modules\PaymentManagement\Repositories\PaymentMethodRepository;
use Modules\PaymentManagement\Requests\StorePaymentMethodRequest;

class AdminPaymentMethodsController extends Controller
{
    public function __construct(private PaymentMethodRepository $methods)
    {
    }

    public function index()
    {
        return Inertia::render('Admin/PaymentMethods', [
            'methods' => $this->methods->listForAdmin(),
        ]);
    }

    public function store(StorePaymentMethodRequest $request)
    {
        $method = $this->methods->create($this->payload($request));

        return back()->with('success', 'Payment method created: '.$method->name);
    }

    public function update(StorePaymentMethodRequest $request, PaymentMethod $method)
    {
        $this->methods->update($method, $this->payload($request, $method));

        return back()->with('success', 'Payment method updated: '.$method->name);
    }

    public function destroy(PaymentMethod $method)
    {
        abort_if($method->transactions()->exists(), 422, 'Cannot delete a payment method with recorded payments.');

        $this->methods->destroy($method);

        return back()->with('success', 'Payment method deleted.');
    }

    public function qr(PaymentMethod $method)
    {
        abort_unless($method->qr_image_path && Storage::disk('private')->exists($method->qr_image_path), 404);

        return Storage::disk('private')->response($method->qr_image_path);
    }

    protected function payload(StorePaymentMethodRequest $request, ?PaymentMethod $method = null): array
    {
        $data = $request->safe()->except('qr_image');

        if ($request->hasFile('qr_image')) {
            if ($method?->qr_image_path) {
                Storage::disk('private')->delete($method->qr_image_path);
            }
            $data['qr_image_path'] = $request->file('qr_image')->store('payment-methods', 'private');
        }

        return $data;
    }
}
