<?php

namespace App\Domain\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Sales\Http\Requests\CustomerStoreRequest;
use App\Domain\Sales\Http\Requests\CustomerUpdateRequest;
use App\Domain\Sales\Http\Resources\CustomerResource;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $businessId = $request->user()->business_id;

        $customers = Customer::query()
            ->where('business_id', $businessId)
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('customer_type'), fn ($query) => $query->where('customer_type', $request->string('customer_type')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Sales/Customers/Index', [
            'customers' => CustomerResource::collection($customers),
            'filters' => $request->only(['search', 'customer_type']),
        ]);
    }

    public function store(CustomerStoreRequest $request): RedirectResponse
    {
        $this->customerService->create([
            ...$request->validated(),
            'business_id' => $request->user()->business_id,
        ]);

        return back()->with('status', 'customer-created');
    }

    public function update(CustomerUpdateRequest $request, Customer $customer): RedirectResponse
    {
        $this->customerService->update($customer, $request->validated());

        return back()->with('status', 'customer-updated');
    }

    public function deactivate(Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->customerService->deactivate($customer);

        return back()->with('status', 'customer-deactivated');
    }

    public function activate(Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);
        $this->customerService->activate($customer);

        return back()->with('status', 'customer-activated');
    }
}
