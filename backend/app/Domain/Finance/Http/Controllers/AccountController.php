<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Finance\Exceptions\AccountException;
use App\Domain\Finance\Http\Requests\AccountStoreRequest;
use App\Domain\Finance\Http\Requests\AccountUpdateRequest;
use App\Domain\Finance\Http\Resources\AccountResource;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Services\ChartOfAccountsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $accounts = Account::query()
            ->where('business_id', $request->user()->business_id)
            ->with('parent')
            ->orderBy('code')
            ->get();

        return Inertia::render('Finance/Accounts/Index', [
            'accounts' => AccountResource::collection($accounts),
        ]);
    }

    public function store(AccountStoreRequest $request): RedirectResponse
    {
        $this->chartOfAccountsService->create($request->user()->business_id, $request->validated());

        return back()->with('status', 'account-created');
    }

    public function update(AccountUpdateRequest $request, Account $account): RedirectResponse
    {
        $this->chartOfAccountsService->update($account, $request->validated());

        return back()->with('status', 'account-updated');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        try {
            $this->chartOfAccountsService->delete($account);
        } catch (AccountException $e) {
            throw ValidationException::withMessages(['account' => $e->getMessage()]);
        }

        return back()->with('status', 'account-deleted');
    }
}
