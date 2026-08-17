<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Real, live search across the platform's own tables — no search index,
 * no fabricated results. Scoped to the entities that exist today
 * (Businesses, tenant Users, Platform Admins, Subscriptions); other
 * entity types from the original wishlist (Payments, Licenses, Reports,
 * ...) can be added the same way once there's a reason to prioritize them.
 */
class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('q'));

        if ($query === '' || mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $like = "%{$query}%";

        $businesses = Business::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->limit(5)
            ->get(['id', 'name', 'slug', 'email', 'status'])
            ->map(fn (Business $business) => [
                'type' => 'Business',
                'id' => $business->id,
                'title' => $business->name,
                'subtitle' => $business->email,
                'badge' => $business->status,
                'href' => route('platform.businesses.index'),
            ]);

        $users = User::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'type' => 'User',
                'id' => $user->id,
                'title' => $user->name,
                'subtitle' => $user->email,
                'badge' => null,
                'href' => route('platform.users.index'),
            ]);

        $admins = PlatformUser::query()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->map(fn (PlatformUser $admin) => [
                'type' => 'Platform Admin',
                'id' => $admin->id,
                'title' => $admin->name,
                'subtitle' => $admin->email,
                'badge' => null,
                'href' => route('platform.staff.index'),
            ]);

        $subscriptions = Subscription::query()
            ->with('business:id,name')
            ->whereHas('business', fn ($q) => $q->where('name', 'like', $like))
            ->limit(5)
            ->get()
            ->map(fn (Subscription $subscription) => [
                'type' => 'Subscription',
                'id' => $subscription->id,
                'title' => $subscription->business?->name ?? 'Unknown business',
                'subtitle' => 'Status: '.$subscription->status,
                'badge' => $subscription->status,
                'href' => route('platform.subscriptions.dashboard'),
            ]);

        $results = $businesses->concat($users)->concat($admins)->concat($subscriptions)->values();

        return response()->json(['results' => $results]);
    }
}
