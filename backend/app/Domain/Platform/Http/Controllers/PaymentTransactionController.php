<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Finance\Services\PaymentTransactionService;
use App\Domain\Platform\Http\Requests\PaymentTransactionManualRequest;
use App\Domain\Platform\Http\Requests\PaymentTransactionRefundRequest;
use App\Domain\Platform\Http\Resources\PaymentTransactionResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $transactions = PaymentTransaction::query()
            ->with(['business', 'gateway'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where(function ($q) use ($search) {
                    $q->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
                        ->orWhere('external_transaction_id', 'like', "%{$search}%")
                        ->orWhereHas('business', fn ($b) => $b->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->string('payment_method')))
            ->when($request->filled('gateway_id'), fn ($q) => $q->where('payment_gateway_id', $request->string('gateway_id')))
            ->when($request->filled('business_id'), fn ($q) => $q->where('business_id', $request->string('business_id')))
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', $request->string('currency')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')))
            ->orderBy($request->string('sort_by', 'created_at'), $request->string('sort_direction', 'desc'))
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Platform/Finance/Payments/Index', [
            'transactions' => PaymentTransactionResource::collection($transactions),
            'filters' => $request->only([
                'search', 'status', 'type', 'payment_method', 'gateway_id', 'business_id', 'currency', 'date_from', 'date_to', 'sort_by', 'sort_direction',
            ]),
        ]);
    }

    public function show(PaymentTransaction $paymentTransaction): Response
    {
        $paymentTransaction->load(['business.owner', 'gateway', 'timeline', 'refunds', 'gatewayLogs', 'parentTransaction']);

        return Inertia::render('Platform/Finance/Payments/Show', [
            'transaction' => new PaymentTransactionResource($paymentTransaction),
        ]);
    }

    public function store(PaymentTransactionManualRequest $request, PaymentTransactionService $service): RedirectResponse
    {
        $service->recordManual($request->validated(), $request->user());

        return back()->with('status', 'payment-recorded');
    }

    public function retry(PaymentTransaction $paymentTransaction, PaymentTransactionService $service): RedirectResponse
    {
        try {
            $service->retry($paymentTransaction, request()->user());
        } catch (\Throwable $e) {
            return back()->withErrors(['transaction' => $e->getMessage()]);
        }

        return back()->with('status', 'payment-retried');
    }

    public function refund(PaymentTransactionRefundRequest $request, PaymentTransaction $paymentTransaction, PaymentTransactionService $service): RedirectResponse
    {
        try {
            $service->refund($paymentTransaction, (string) $request->validated('amount'), $request->user(), $request->validated('reason'));
        } catch (\Throwable $e) {
            return back()->withErrors(['transaction' => $e->getMessage()]);
        }

        return back()->with('status', 'payment-refunded');
    }

    public function approve(PaymentTransaction $paymentTransaction, PaymentTransactionService $service): RedirectResponse
    {
        $service->manuallyApprove($paymentTransaction, request()->user());

        return back()->with('status', 'payment-approved');
    }
}
