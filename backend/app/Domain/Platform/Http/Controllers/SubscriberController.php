<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\BroadcastEmail;
use App\Domain\Platform\Http\Resources\SubscriberResource;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class SubscriberController extends Controller
{
    public function index(Request $request): Response
    {
        $subscriptions = Subscription::query()
            ->with(['business.owner', 'plan'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->whereHas('business', fn ($b) => $b->where('name', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Platform/Subscriptions/Subscribers/Index', [
            'subscribers'        => SubscriberResource::collection($subscriptions),
            'filters'            => $request->only(['search', 'status']),
            'plans'              => SubscriptionPlan::query()->orderBy('sort_order')->get(['id', 'name']),
            'ownerEmailCount'    => Subscription::query()
                ->with('business.owner')
                ->get()
                ->filter(fn ($s) => filled($s->business?->owner?->email))
                ->unique(fn ($s) => $s->business->owner->email)
                ->count(),
        ]);
    }

    public function assignPlan(Request $request, Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,quarterly,yearly,lifetime'],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $plan = SubscriptionPlan::query()->findOrFail($validated['subscription_plan_id']);

        $subscriptions->changePlan(
            $subscription,
            $plan,
            $validated['billing_cycle'],
            $validated['custom_price'] ?? null,
        );

        return back()->with('status', 'plan-assigned');
    }

    public function extendTrial(Request $request, Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $subscriptions->extendTrial($subscription, $validated['days']);

        return back()->with('status', 'trial-extended');
    }

    public function renew(Request $request, Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $subscriptions->renewWithPayment($subscription, [
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'subscription-renewed');
    }

    public function suspend(Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscriptions->suspend($subscription);

        return back()->with('status', 'subscription-suspended');
    }

    public function restore(Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscriptions->restore($subscription);

        return back()->with('status', 'subscription-restored');
    }

    public function cancel(Subscription $subscription, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscriptions->cancel($subscription);

        return back()->with('status', 'subscription-canceled');
    }

    public function sendBroadcast(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:10000'],
        ]);

        $sent  = 0;
        $seen  = [];

        Subscription::query()->with('business.owner')->get()
            ->each(function (Subscription $subscription) use ($validated, &$sent, &$seen) {
                $owner = $subscription->business?->owner;

                if (! $owner || blank($owner->email) || isset($seen[$owner->email])) {
                    return;
                }

                Mail::to($owner->email)
                    ->send(new BroadcastEmail($validated['subject'], $validated['body'], $owner->name));

                $seen[$owner->email] = true;
                $sent++;
            });

        return back()->with('broadcast_count', $sent);
    }

    public function sendEmail(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:10000'],
        ]);

        $owner = $subscription->load('business.owner')->business?->owner;

        if ($owner && filled($owner->email)) {
            Mail::to($owner->email)
                ->send(new BroadcastEmail($validated['subject'], $validated['body'], $owner->name));
        }

        return back()->with('status', 'email-sent');
    }
}
