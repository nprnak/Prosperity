<?php

namespace Modules\JarManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\JarManagement\Enums\CustomerType;
use Modules\JarManagement\Models\JarCustomer;
use Modules\JarManagement\Repositories\JarCustomerRepository;
use Modules\JarManagement\Requests\StoreJarCustomerRequest;

class JarCustomerController extends Controller
{
    public function __construct(private readonly JarCustomerRepository $customers) {}

    public function index(Request $request)
    {
        return Inertia::render('JarManagement/Customers/Index', [
            'customers' => $this->customers->search($request->string('q')->toString() ?: null),
            'typeOptions' => CustomerType::options(),
            'filters' => $request->only('q'),
        ]);
    }

    public function store(StoreJarCustomerRequest $request)
    {
        $customer = JarCustomer::create([...$request->validated(), 'created_by' => $request->user()->id]);

        return back()->with('success', "Customer {$customer->name} added.");
    }

    public function show(JarCustomer $customer)
    {
        $customer->load(['deliveries' => fn ($q) => $q->latest('delivered_at')->limit(50)->with(['items.jar', 'returns.jar'])]);

        return Inertia::render('JarManagement/Customers/Show', [
            'customer' => $customer,
        ]);
    }

    public function update(StoreJarCustomerRequest $request, JarCustomer $customer)
    {
        $customer->update($request->validated());

        return back()->with('success', "Customer {$customer->name} updated.");
    }
}
