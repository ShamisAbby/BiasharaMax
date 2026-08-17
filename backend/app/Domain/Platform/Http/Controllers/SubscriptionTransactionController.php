<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Platform\Http\Resources\SubscriptionTransactionResource;
use App\Domain\Subscription\Models\SubscriptionTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $transactions = SubscriptionTransaction::query()
            ->with(['business', 'recordedBy'])
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->whereHas('business', fn ($b) => $b->where('name', 'like', "%{$search}%"));
            })
            ->latest('paid_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Platform/Subscriptions/Transactions/Index', [
            'transactions' => SubscriptionTransactionResource::collection($transactions),
            'filters' => $request->only(['search']),
        ]);
    }
}
