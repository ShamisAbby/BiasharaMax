<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\BroadcastEmail;
use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use App\Domain\Platform\Http\Resources\PlatformUserResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PlatformUserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->value();
        $type   = $request->string('type', 'all')->value();

        if ($type === 'admin') {
            $paginator = PlatformUser::query()
                ->with('platformRole')
                ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString();

            $rows = collect($paginator->items())->map(fn (PlatformUser $u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'email'        => $u->email,
                'status'       => $u->status,
                'user_type'    => 'admin',
                'is_owner'     => false,
                'business'     => null,
                'role'         => $u->platformRole
                                    ? ['id' => $u->platform_role_id, 'name' => $u->platformRole->name]
                                    : ['id' => null, 'name' => 'Super Admin'],
                'last_login_at' => $u->last_login_at,
                'created_at'   => $u->created_at,
            ])->values()->all();

            return Inertia::render('Platform/Users/Index', [
                'users'            => [
                    'data' => $rows,
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page'    => $paginator->lastPage(),
                        'total'        => $paginator->total(),
                        'links'        => $paginator->linkCollection()->toArray(),
                    ],
                ],
                'filters'           => $request->only(['search', 'status', 'type']),
                'activeUserCount'   => User::query()->where('status', User::STATUS_ACTIVE)->count(),
                'adminCount'        => PlatformUser::count(),
                'businessUserCount' => User::count(),
            ]);
        }

        // 'business' or 'all' — show tenant users
        $users = User::query()
            ->with(['business', 'role'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Platform/Users/Index', [
            'users'            => PlatformUserResource::collection($users),
            'filters'          => $request->only(['search', 'status', 'type']),
            'activeUserCount'  => User::query()->where('status', User::STATUS_ACTIVE)->count(),
            'adminCount'       => PlatformUser::count(),
            'businessUserCount' => User::count(),
        ]);
    }

    public function activate(User $user): RedirectResponse
    {
        $user->update(['status' => User::STATUS_ACTIVE]);

        return back()->with('status', 'user-activated');
    }

    public function deactivate(User $user): RedirectResponse
    {
        $user->update(['status' => User::STATUS_SUSPENDED]);

        return back()->with('status', 'user-deactivated');
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        Password::sendResetLink(['email' => $user->email]);

        return back()->with('status', 'password-reset-sent');
    }

    public function sendBroadcast(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:10000'],
        ]);

        $users = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->get(['name', 'email']);

        foreach ($users as $user) {
            Mail::to($user->email)
                ->send(new BroadcastEmail($validated['subject'], $validated['body'], $user->name));
        }

        return back()->with('broadcast_count', $users->count());
    }

    public function sendEmail(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:10000'],
        ]);

        Mail::to($user->email)
            ->send(new BroadcastEmail($validated['subject'], $validated['body'], $user->name));

        return back()->with('status', 'email-sent');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless($user->status === User::STATUS_SUSPENDED, 403, 'User must be deactivated before deletion.');

        $user->delete();

        return back()->with('status', 'user-deleted');
    }
}
