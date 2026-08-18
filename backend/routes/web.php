<?php

use Inertia\Inertia;
use App\Http\Controllers\HomeController;
use App\Domain\Support\Http\Controllers\BusinessSupportTicketController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SearchController;
use App\Domain\AiInsights\Http\Controllers\BusinessAssistantController;
use App\Domain\Accounting\Http\Controllers\AccountingDashboardController;
use App\Domain\Accounting\Http\Controllers\ExpenseCategoryController;
use App\Domain\Accounting\Http\Controllers\ExpenseController;
use App\Domain\Accounting\Http\Controllers\IncomeController;
use App\Domain\Accounting\Http\Controllers\ProfitAndLossController;
use App\Domain\Authentication\Http\Controllers\AcceptInvitationController;
use App\Domain\Authentication\Http\Controllers\ProfileController;
use App\Domain\Business\Http\Controllers\BranchController;
use App\Domain\Business\Http\Controllers\BusinessSettingsController;
use App\Domain\Business\Http\Controllers\DashboardController;
use App\Domain\Backup\Http\Controllers\BusinessBackupController;
use App\Domain\Business\Http\Controllers\EmployeeController;
use App\Domain\Business\Http\Controllers\WarehouseController;
use App\Domain\CRM\Http\Controllers\CrmCustomerController;
use App\Domain\CRM\Http\Controllers\CrmDashboardController;
use App\Domain\CRM\Http\Controllers\CustomerFeedbackController;
use App\Domain\CRM\Http\Controllers\CustomerGroupController;
use App\Domain\CRM\Http\Controllers\CustomerTagController;
use App\Domain\CRM\Http\Controllers\LoyaltyDashboardController;
use App\Domain\CRM\Http\Controllers\MarketingCampaignController;
use App\Domain\CRM\Http\Controllers\LoyaltyRewardController;
use App\Domain\CRM\Http\Controllers\LoyaltyTierController;
use App\Domain\Finance\Http\Controllers\AccountController;
use App\Domain\Finance\Http\Controllers\BankAccountController;
use App\Domain\Finance\Http\Controllers\BankReconciliationController;
use App\Domain\Finance\Http\Controllers\BudgetController;
use App\Domain\Finance\Http\Controllers\CurrencyController;
use App\Domain\Finance\Http\Controllers\FinanceDashboardController;
use App\Domain\Finance\Http\Controllers\FixedAssetController;
use App\Domain\Finance\Http\Controllers\TaxController;
use App\Domain\Payroll\Http\Controllers\AttendanceController;
use App\Domain\Payroll\Http\Controllers\EmployeeProfileController;
use App\Domain\Payroll\Http\Controllers\HrDashboardController;
use App\Domain\Payroll\Http\Controllers\LeaveController;
use App\Domain\Payroll\Http\Controllers\LeaveTypeController;
use App\Domain\Payroll\Http\Controllers\PayrollPeriodController;
use App\Domain\Reports\Http\Controllers\ReportCenterController;
use App\Domain\Finance\Http\Controllers\FinancialPeriodController;
use App\Domain\Finance\Http\Controllers\FinancialReportController;
use App\Domain\Finance\Http\Controllers\GeneralLedgerController;
use App\Domain\Finance\Http\Controllers\JournalEntryController;
use App\Domain\Inventory\Http\Controllers\AttributeController;
use App\Domain\Inventory\Http\Controllers\BrandController;
use App\Domain\Inventory\Http\Controllers\CategoryController;
use App\Domain\Inventory\Http\Controllers\CollectionController;
use App\Domain\Inventory\Http\Controllers\InventoryCountController;
use App\Domain\Inventory\Http\Controllers\InventoryDashboardController;
use App\Domain\Inventory\Http\Controllers\InventoryImportController;
use App\Domain\Inventory\Http\Controllers\ProductController;
use App\Domain\Inventory\Http\Controllers\StockAdjustmentController;
use App\Domain\Inventory\Http\Controllers\StockTransferController;
use App\Domain\Inventory\Http\Controllers\TagController;
use App\Domain\Inventory\Http\Controllers\UnitController;
use App\Domain\Purchasing\Http\Controllers\GoodsReceivedNoteController;
use App\Domain\Purchasing\Http\Controllers\PurchaseOrderController;
use App\Domain\Purchasing\Http\Controllers\PurchasingDashboardController;
use App\Domain\Purchasing\Http\Controllers\SupplierController;
use App\Domain\RBAC\Http\Controllers\RoleController;
use App\Domain\Sales\Http\Controllers\CustomerController;
use App\Domain\Sales\Http\Controllers\POSController;
use App\Domain\Sales\Http\Controllers\SaleController;
use App\Domain\Sales\Http\Controllers\SaleReturnController;
use App\Domain\Sales\Http\Controllers\SalesDashboardController;
use App\Domain\Subscription\Http\Controllers\SubscriptionController;
use App\Domain\Website\Http\Controllers\ArticleController;
use App\Domain\Website\Http\Controllers\BusinessWebsiteController;
use App\Domain\Website\Http\Controllers\ProductEnquiryController;
use App\Domain\Website\Http\Controllers\PublicBlogController;
use App\Domain\Website\Http\Controllers\StorefrontController;
use App\Domain\WebsiteTemplates\Http\Controllers\PublicWebsiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// `website.not-withdrawn` 404s the entire public surface once an owner has
// explicitly unpublished — homepage, storefront, cart, checkout and blog
// alike. Grouped rather than applied per-route so a new public page added
// under this prefix inherits it automatically.
Route::middleware('website.not-withdrawn')->group(function () {
    Route::get('/site/{business:slug}', [PublicWebsiteController::class, 'show'])->name('public.website.show');

    Route::prefix('site/{business:slug}')->name('public.website.')->group(function () {
        Route::get('/products', [StorefrontController::class, 'products'])->name('products.index');
        Route::get('/products/{slug}', [StorefrontController::class, 'productShow'])->name('products.show');
        Route::post('/products/{slug}/enquiries', [StorefrontController::class, 'storeEnquiry'])->name('products.enquiries.store');

        Route::get('/cart', [StorefrontController::class, 'cart'])->name('cart.show');
        Route::post('/cart', [StorefrontController::class, 'addToCart'])->name('cart.add');
        Route::patch('/cart/{product}', [StorefrontController::class, 'updateCartItem'])->name('cart.update');
        Route::delete('/cart/{product}', [StorefrontController::class, 'removeCartItem'])->name('cart.remove');

        Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('checkout.show');
        Route::post('/checkout', [StorefrontController::class, 'placeOrder'])->name('checkout.store');
        Route::get('/orders/{saleNumber}', [StorefrontController::class, 'orderShow'])->name('orders.show');

        Route::get('/blog', [PublicBlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/{slug}', [PublicBlogController::class, 'show'])->name('blog.show');
    });
});

Route::middleware('signed')->group(function () {
    Route::get('/employee-invitations/{user}', [AcceptInvitationController::class, 'show'])
        ->name('employee-invitations.accept');
    Route::post('/employee-invitations/{user}', [AcceptInvitationController::class, 'store'])
        ->name('employee-invitations.store');
});

// PWA onboarding (guest) + public code validation
Route::middleware('guest')->group(function () {
    Route::get('/onboarding', fn () => Inertia::render('Onboarding'))->name('onboarding');
    Route::get('/register/license', [App\Domain\Business\Http\Controllers\BusinessRegistrationController::class, 'createWithCode'])->name('register.license');
});
/*
 * Snippe payment webhook.
 *
 * Public and unauthenticated by necessity — Snippe's servers call it, not a
 * logged-in browser. The HMAC signature is the only thing authorising it,
 * which is why the verifier refuses anything without a fresh timestamp.
 *
 * CSRF-exempt: see `bootstrap/app.php`, where the path is excluded. A CSRF
 * token cannot exist on a server-to-server POST, so without that exclusion
 * every webhook would be rejected with a 419 and Snippe would retry five
 * times into the same wall.
 */
Route::post('/webhooks/snippe', App\Domain\Finance\Http\Controllers\SnippeWebhookController::class)
    ->name('webhooks.snippe');

Route::post('/registration-codes/validate', App\Domain\Subscription\Http\Controllers\RegistrationCodeValidationController::class)->name('registration-codes.validate');

/*
 * Where a suspended business is sent. Authenticated, but deliberately
 * outside the `subscription.active` group — that middleware is what
 * redirects here, so putting this route behind it would bounce the
 * request between the two forever.
 *
 * `verified` is omitted too: an unverified owner whose business gets
 * suspended still needs to be told why, and sending them to a "verify
 * your email" screen instead answers a question they did not ask.
 */
Route::middleware('auth')
    ->get('/suspended', App\Domain\Business\Http\Controllers\SuspendedBusinessController::class)
    ->name('suspended');

/*
 * The expired-plan screen and its renew action. Outside the
 * `subscription.active` group for the same reason as `/suspended`: this is
 * where that gate sends people, so gating it would loop.
 *
 * Separate from `/suspended` because an expired plan and a suspended
 * account are different messages. One offers a renew button; the other
 * must not, because paying does not lift a suspension.
 */
Route::middleware('auth')->group(function () {
    Route::get('/plan-expired', [App\Domain\Subscription\Http\Controllers\PlanRenewalController::class, 'show'])
        ->name('plan.expired');

    Route::post('/plan-expired/renew/{plan}', [App\Domain\Subscription\Http\Controllers\PlanRenewalController::class, 'renew'])
        ->name('subscription.renew');
});

Route::middleware(['auth', 'verified', 'subscription.active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/search', SearchController::class)->name('search');

    Route::post('/assistant/ask', BusinessAssistantController::class)->name('assistant.ask');

    /*
     * Support tickets to the platform team.
     *
     * Outside every module gate on purpose. If a business's Sales module
     * were switched off by mistake, "I can't reach Sales" is exactly the
     * ticket they need to raise — routing support through the same
     * gating would make the tool for reporting a problem the first
     * casualty of that problem.
     *
     * `{ticket}` is a plain string, not a bound model: SupportTicket has
     * no tenant scope (platform admins must see all businesses'), so the
     * controller resolves it through a business-scoped query instead.
     */
    Route::prefix('support')->name('support.')->group(function () {
        Route::get('/', [BusinessSupportTicketController::class, 'index'])->name('index');
        Route::post('/', [BusinessSupportTicketController::class, 'store'])->name('store');
        Route::get('/{ticket}', [BusinessSupportTicketController::class, 'show'])->name('show');
        Route::post('/{ticket}/reply', [BusinessSupportTicketController::class, 'reply'])->name('reply');
        Route::post('/{ticket}/close', [BusinessSupportTicketController::class, 'close'])->name('close');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    // Destructive, unlike read-all — these rows are deleted. The UI
    // confirms before calling it.
    Route::delete('/notifications/clear', [NotificationController::class, 'clear'])->name('notifications.clear');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/settings/business', [BusinessSettingsController::class, 'edit'])->middleware('module:business')->name('settings.business.edit');
    Route::patch('/settings/business', [BusinessSettingsController::class, 'update'])->middleware('module:business')->name('settings.business.update');

    Route::get('/settings/subscription', [SubscriptionController::class, 'show'])->middleware('module:settings')->name('settings.subscription.show');

    Route::get('/settings/roles', [RoleController::class, 'index'])->middleware('module:settings')->name('settings.roles.index');
    Route::post('/settings/roles', [RoleController::class, 'store'])->middleware('module:settings')->name('settings.roles.store');
    Route::patch('/settings/roles/{role}', [RoleController::class, 'update'])->middleware('module:settings')->name('settings.roles.update');
    Route::delete('/settings/roles/{role}', [RoleController::class, 'destroy'])->middleware('module:settings')->name('settings.roles.destroy');
    Route::post('/settings/roles/{role}/clone', [RoleController::class, 'clone'])->middleware('module:settings')->name('settings.roles.clone');
    Route::post('/settings/roles/{role}/apply-template', [RoleController::class, 'applyTemplate'])->middleware('module:settings')->name('settings.roles.apply-template');

    Route::get('/settings/branches', [BranchController::class, 'index'])->middleware('module:business')->name('settings.branches.index');
    Route::post('/settings/branches', [BranchController::class, 'store'])->middleware('module:business')->name('settings.branches.store');
    Route::patch('/settings/branches/{branch}', [BranchController::class, 'update'])->middleware('module:business')->name('settings.branches.update');
    Route::delete('/settings/branches/{branch}', [BranchController::class, 'destroy'])->middleware('module:business')->name('settings.branches.destroy');

    Route::get('/settings/warehouses', [WarehouseController::class, 'index'])->middleware('module:business')->name('settings.warehouses.index');
    Route::post('/settings/warehouses', [WarehouseController::class, 'store'])->middleware('module:business')->name('settings.warehouses.store');
    Route::patch('/settings/warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('module:business')->name('settings.warehouses.update');
    Route::delete('/settings/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('module:business')->name('settings.warehouses.destroy');

    // Business-scope backup. Deliberately separate from the platform's
    // backup screen: this exports only this business's records and
    // restores only into this business.
    Route::get('/settings/backups', [BusinessBackupController::class, 'index'])->middleware('module:settings')->name('settings.backups.index');
    Route::get('/settings/backups/export', [BusinessBackupController::class, 'export'])->middleware('module:settings')->name('settings.backups.export');
    Route::post('/settings/backups/preview', [BusinessBackupController::class, 'preview'])->middleware('module:settings')->name('settings.backups.preview');
    Route::post('/settings/backups/restore', [BusinessBackupController::class, 'restore'])->middleware('module:settings')->name('settings.backups.restore');
    Route::delete('/settings/backups/pending', [BusinessBackupController::class, 'cancel'])->middleware('module:settings')->name('settings.backups.cancel');

    Route::get('/settings/employees', [EmployeeController::class, 'index'])->middleware('module:employees')->name('settings.employees.index');
    Route::post('/settings/employees', [EmployeeController::class, 'store'])->middleware('module:employees')->name('settings.employees.store');
    Route::patch('/settings/employees/{employee}', [EmployeeController::class, 'update'])->middleware('module:employees')->name('settings.employees.update');
    Route::delete('/settings/employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('module:employees')->name('settings.employees.destroy');

    Route::prefix('inventory')->name('inventory.')->middleware('module:inventory')->group(function () {
        Route::get('/', InventoryDashboardController::class)->name('dashboard');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
        Route::post('/products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');

        Route::post('/import', [InventoryImportController::class, 'store'])->name('import.store');
        Route::get('/export', [InventoryImportController::class, 'export'])->name('export.show');
        // The blank starter file. Deliberately not behind the export
        // permission — it holds no business data, only column headings.
        Route::get('/import/template', [InventoryImportController::class, 'template'])->name('import.template');
        Route::get('/import/{log}/errors', [InventoryImportController::class, 'downloadErrorReport'])->name('import.errors');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
        Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
        Route::patch('/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::patch('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

        Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
        Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

        Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
        Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
        Route::patch('/collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
        Route::delete('/collections/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');

        Route::get('/attributes', [AttributeController::class, 'index'])->name('attributes.index');
        Route::post('/attributes', [AttributeController::class, 'store'])->name('attributes.store');
        Route::patch('/attributes/{attribute}', [AttributeController::class, 'update'])->name('attributes.update');
        Route::delete('/attributes/{attribute}', [AttributeController::class, 'destroy'])->name('attributes.destroy');

        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::patch('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
        Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
        Route::post('/stock-adjustments/{adjustment}/complete', [StockAdjustmentController::class, 'complete'])->name('stock-adjustments.complete');
        Route::delete('/stock-adjustments/{adjustment}', [StockAdjustmentController::class, 'destroy'])->name('stock-adjustments.destroy');

        Route::get('/stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
        Route::post('/stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
        Route::post('/stock-transfers/{transfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('stock-transfers.dispatch');
        Route::post('/stock-transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive');
        Route::post('/stock-transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');

        Route::get('/counts', [InventoryCountController::class, 'index'])->name('counts.index');
        Route::post('/counts', [InventoryCountController::class, 'store'])->name('counts.store');
        Route::patch('/counts/items/{item}', [InventoryCountController::class, 'recordItem'])->name('counts.items.record');
        Route::post('/counts/{count}/complete', [InventoryCountController::class, 'complete'])->name('counts.complete');
    });

    Route::prefix('sales')->name('sales.')->middleware('module:sales')->group(function () {
        Route::get('/', SalesDashboardController::class)->name('dashboard');

        Route::get('/orders', [SaleController::class, 'index'])->name('orders.index');
        Route::post('/orders', [SaleController::class, 'store'])->name('orders.store');
        Route::get('/orders/{sale}', [SaleController::class, 'show'])->name('orders.show');
        Route::post('/orders/{sale}/void', [SaleController::class, 'void'])->name('orders.void');
        Route::post('/orders/{sale}/payments', [SaleController::class, 'recordPayment'])->name('orders.payments.store');
        Route::get('/orders/{sale}/returns/create', [SaleReturnController::class, 'create'])->name('orders.returns.create');
        Route::post('/orders/{sale}/returns', [SaleReturnController::class, 'store'])->name('orders.returns.store');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::patch('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{customer}/activate', [CustomerController::class, 'activate'])->name('customers.activate');
        Route::post('/customers/{customer}/deactivate', [CustomerController::class, 'deactivate'])->name('customers.deactivate');

        Route::get('/returns', [SaleReturnController::class, 'index'])->name('returns.index');
        Route::get('/returns/{return}', [SaleReturnController::class, 'show'])->name('returns.show');
        Route::post('/returns/{return}/approve', [SaleReturnController::class, 'approve'])->name('returns.approve');
        Route::post('/returns/{return}/reject', [SaleReturnController::class, 'reject'])->name('returns.reject');
    });

    Route::prefix('accounting')->name('accounting.')->middleware('module:finance')->group(function () {
        Route::get('/', AccountingDashboardController::class)->name('dashboard');
        Route::get('/reports/profit-and-loss', ProfitAndLossController::class)->name('reports.profit-and-loss');

        Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
        Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
        Route::patch('/expense-categories/{category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
        Route::delete('/expense-categories/{category}', [ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');

        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::patch('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
        Route::post('/expenses/{expense}/mark-paid', [ExpenseController::class, 'markPaid'])->name('expenses.mark-paid');

        Route::get('/income', [IncomeController::class, 'index'])->name('income.index');
        Route::post('/income', [IncomeController::class, 'store'])->name('income.store');
        Route::patch('/income/{income}', [IncomeController::class, 'update'])->name('income.update');
        Route::delete('/income/{income}', [IncomeController::class, 'destroy'])->name('income.destroy');
    });

    Route::prefix('finance')->name('finance.')->middleware('module:finance')->group(function () {
        Route::get('/', FinanceDashboardController::class)->name('dashboard');

        Route::prefix('journal')->name('journal.')->group(function () {
            Route::get('/', [JournalEntryController::class, 'index'])->name('index');
            Route::get('/create', [JournalEntryController::class, 'create'])->name('create');
            Route::post('/', [JournalEntryController::class, 'store'])->name('store');
            Route::get('/{entry}', [JournalEntryController::class, 'show'])->name('show');
            Route::post('/{entry}/post', [JournalEntryController::class, 'post'])->name('post');
            Route::post('/{entry}/reverse', [JournalEntryController::class, 'reverse'])->name('reverse');
            Route::post('/{entry}/void', [JournalEntryController::class, 'void'])->name('void');
        });

        Route::prefix('accounts')->name('accounts.')->group(function () {
            Route::get('/', [AccountController::class, 'index'])->name('index');
            Route::post('/', [AccountController::class, 'store'])->name('store');
            Route::patch('/{account}', [AccountController::class, 'update'])->name('update');
            Route::delete('/{account}', [AccountController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('ledger')->name('ledger.')->group(function () {
            Route::get('/', [GeneralLedgerController::class, 'index'])->name('index');
            Route::get('/{account}', [GeneralLedgerController::class, 'show'])->name('show');
        });

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/trial-balance', [FinancialReportController::class, 'trialBalance'])->name('trial-balance');
            Route::get('/trial-balance/pdf', [FinancialReportController::class, 'trialBalancePdf'])->name('trial-balance.pdf');
            Route::get('/profit-and-loss', [FinancialReportController::class, 'profitAndLoss'])->name('profit-and-loss');
            Route::get('/profit-and-loss/pdf', [FinancialReportController::class, 'profitAndLossPdf'])->name('profit-and-loss.pdf');
            Route::get('/balance-sheet', [FinancialReportController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('/balance-sheet/pdf', [FinancialReportController::class, 'balanceSheetPdf'])->name('balance-sheet.pdf');
            Route::get('/cash-flow', [FinancialReportController::class, 'cashFlow'])->name('cash-flow');
            Route::get('/cash-flow/pdf', [FinancialReportController::class, 'cashFlowPdf'])->name('cash-flow.pdf');
        });

        Route::prefix('periods')->name('periods.')->group(function () {
            Route::get('/', [FinancialPeriodController::class, 'index'])->name('index');
            Route::post('/', [FinancialPeriodController::class, 'store'])->name('store');
            Route::post('/seed-year', [FinancialPeriodController::class, 'seedYear'])->name('seed-year');
            Route::patch('/{period}/lock', [FinancialPeriodController::class, 'lock'])->name('lock');
            Route::post('/{period}/close', [FinancialPeriodController::class, 'close'])->name('close');
        });

        Route::prefix('tax')->name('tax.')->group(function () {
            Route::get('/configure', [TaxController::class, 'configure'])->name('configure');
            Route::post('/configure', [TaxController::class, 'saveConfigure'])->name('save-configure');
            Route::get('/vat-return', [TaxController::class, 'vatReturn'])->name('vat-return');
            Route::get('/income-tax', [TaxController::class, 'incomeTaxSummary'])->name('income-tax');
        });

        Route::prefix('assets')->name('assets.')->group(function () {
            Route::get('/', [FixedAssetController::class, 'index'])->name('index');
            Route::post('/', [FixedAssetController::class, 'store'])->name('store');
            Route::get('/{asset}', [FixedAssetController::class, 'show'])->name('show');
            Route::delete('/{asset}', [FixedAssetController::class, 'destroy'])->name('destroy');
            Route::post('/{asset}/dispose', [FixedAssetController::class, 'dispose'])->name('dispose');
            Route::post('/post-depreciation', [FixedAssetController::class, 'postDepreciation'])->name('post-depreciation');
        });

        Route::prefix('budgets')->name('budgets.')->group(function () {
            Route::get('/', [BudgetController::class, 'index'])->name('index');
            Route::post('/', [BudgetController::class, 'store'])->name('store');
            Route::get('/{budget}', [BudgetController::class, 'show'])->name('show');
            Route::delete('/{budget}', [BudgetController::class, 'destroy'])->name('destroy');
            Route::post('/{budget}/approve', [BudgetController::class, 'approve'])->name('approve');
            Route::post('/{budget}/activate', [BudgetController::class, 'activate'])->name('activate');
        });

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies');
            Route::post('/currencies/enable', [CurrencyController::class, 'enable'])->name('currencies.enable');
            Route::delete('/currencies/{currencyId}', [CurrencyController::class, 'disable'])->name('currencies.disable');
        });

        Route::prefix('bank')->name('bank.')->group(function () {
            Route::get('/', [BankAccountController::class, 'index'])->name('index');
            Route::post('/', [BankAccountController::class, 'store'])->name('store');
            Route::post('/transfer', [BankAccountController::class, 'transfer'])->name('transfer');
            Route::get('/{bankAccount}', [BankAccountController::class, 'show'])->name('show');
            Route::patch('/{bankAccount}', [BankAccountController::class, 'update'])->name('update');
            Route::delete('/{bankAccount}', [BankAccountController::class, 'destroy'])->name('destroy');
            Route::prefix('{bankAccount}/reconciliations')->name('reconciliations.')->group(function () {
                Route::get('/', [BankReconciliationController::class, 'index'])->name('index');
                Route::post('/', [BankReconciliationController::class, 'start'])->name('start');
                Route::patch('/{reconciliation}/items/{transaction}', [BankReconciliationController::class, 'markItem'])->name('mark-item');
                Route::post('/{reconciliation}/complete', [BankReconciliationController::class, 'complete'])->name('complete');
            });
        });
    });

    Route::prefix('reports')->name('reports.')->middleware('module:reports')->group(function () {
        Route::get('/', [ReportCenterController::class, 'index'])->name('index');
        Route::get('/{key}', [ReportCenterController::class, 'show'])->name('show')->where('key', '[a-z0-9._-]+');
    });

    Route::prefix('payroll')->name('payroll.')->middleware('module:employees')->group(function () {
        Route::get('/dashboard', HrDashboardController::class)->name('dashboard');

        Route::prefix('employees')->name('employees.')->group(function () {
            Route::get('/', [EmployeeProfileController::class, 'index'])->name('index');
            Route::post('/', [EmployeeProfileController::class, 'store'])->name('store');
            Route::get('/{profile}', [EmployeeProfileController::class, 'show'])->name('show');
            Route::patch('/{profile}', [EmployeeProfileController::class, 'update'])->name('update');
        });

        Route::prefix('periods')->name('periods.')->group(function () {
            Route::get('/', [PayrollPeriodController::class, 'index'])->name('index');
            Route::post('/', [PayrollPeriodController::class, 'store'])->name('store');
            Route::get('/{period}', [PayrollPeriodController::class, 'show'])->name('show');
            Route::post('/{period}/generate', [PayrollPeriodController::class, 'generate'])->name('generate');
            Route::post('/{period}/approve', [PayrollPeriodController::class, 'approve'])->name('approve');
            Route::post('/{period}/pay', [PayrollPeriodController::class, 'pay'])->name('pay');
        });

        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', [AttendanceController::class, 'index'])->name('index');
            Route::post('/clock-in', [AttendanceController::class, 'clockIn'])->name('clock-in');
            Route::post('/{record}/clock-out', [AttendanceController::class, 'clockOut'])->name('clock-out');
            Route::post('/{record}/break-start', [AttendanceController::class, 'startBreak'])->name('break-start');
            Route::post('/{record}/break-end', [AttendanceController::class, 'endBreak'])->name('break-end');
            Route::post('/manual', [AttendanceController::class, 'manualRecord'])->name('manual');
            Route::get('/corrections', [AttendanceController::class, 'corrections'])->name('corrections.index');
            Route::post('/{record}/corrections', [AttendanceController::class, 'storeCorrection'])->name('corrections.store');
            Route::post('/corrections/{correction}/approve', [AttendanceController::class, 'approveCorrection'])->name('corrections.approve');
            Route::post('/corrections/{correction}/reject', [AttendanceController::class, 'rejectCorrection'])->name('corrections.reject');
        });

        Route::prefix('leave')->name('leave.')->group(function () {
            Route::get('/', [LeaveController::class, 'index'])->name('index');
            Route::post('/', [LeaveController::class, 'store'])->name('store');
            Route::post('/{leaveRequest}/approve', [LeaveController::class, 'approve'])->name('approve');
            Route::post('/{leaveRequest}/reject', [LeaveController::class, 'reject'])->name('reject');
            Route::delete('/{leaveRequest}', [LeaveController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('leave-types')->name('leave-types.')->group(function () {
            Route::get('/', [LeaveTypeController::class, 'index'])->name('index');
            Route::post('/', [LeaveTypeController::class, 'store'])->name('store');
            Route::patch('/{leaveType}', [LeaveTypeController::class, 'update'])->name('update');
            Route::delete('/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('destroy');
            Route::post('/allocate', [LeaveTypeController::class, 'allocate'])->name('allocate');
        });
    });

    Route::prefix('crm')->name('crm.')->middleware('module:crm')->group(function () {
        Route::get('/', CrmDashboardController::class)->name('dashboard');

        Route::get('/customer-groups', [CustomerGroupController::class, 'index'])->name('customer-groups.index');
        Route::post('/customer-groups', [CustomerGroupController::class, 'store'])->name('customer-groups.store');
        Route::patch('/customer-groups/{group}', [CustomerGroupController::class, 'update'])->name('customer-groups.update');
        Route::delete('/customer-groups/{group}', [CustomerGroupController::class, 'destroy'])->name('customer-groups.destroy');

        Route::get('/customer-tags', [CustomerTagController::class, 'index'])->name('customer-tags.index');
        Route::post('/customer-tags', [CustomerTagController::class, 'store'])->name('customer-tags.store');
        Route::patch('/customer-tags/{tag}', [CustomerTagController::class, 'update'])->name('customer-tags.update');
        Route::delete('/customer-tags/{tag}', [CustomerTagController::class, 'destroy'])->name('customer-tags.destroy');

        Route::get('/customers/{customer}', [CrmCustomerController::class, 'show'])->name('customers.show');
        Route::post('/customers/{customer}/notes', [CrmCustomerController::class, 'storeNote'])->name('customers.notes.store');
        Route::delete('/customers/{customer}/notes/{note}', [CrmCustomerController::class, 'destroyNote'])->name('customers.notes.destroy');
        Route::patch('/customers/{customer}/tags', [CrmCustomerController::class, 'syncTags'])->name('customers.tags.update');
        Route::patch('/customers/{customer}/group', [CrmCustomerController::class, 'assignGroup'])->name('customers.group.update');
        Route::post('/customers/{customer}/loyalty', [CrmCustomerController::class, 'adjustLoyalty'])->name('customers.loyalty.adjust');
        Route::post('/customers/{customer}/loyalty/redeem', [CrmCustomerController::class, 'redeemReward'])->name('customers.loyalty.redeem');
        Route::get('/customers/{customer}/card', [CrmCustomerController::class, 'card'])->name('customers.card');

        Route::get('/loyalty', LoyaltyDashboardController::class)->name('loyalty.dashboard');

        Route::get('/loyalty-tiers', [LoyaltyTierController::class, 'index'])->name('loyalty-tiers.index');
        Route::post('/loyalty-tiers', [LoyaltyTierController::class, 'store'])->name('loyalty-tiers.store');
        Route::patch('/loyalty-tiers/{tier}', [LoyaltyTierController::class, 'update'])->name('loyalty-tiers.update');
        Route::delete('/loyalty-tiers/{tier}', [LoyaltyTierController::class, 'destroy'])->name('loyalty-tiers.destroy');

        Route::get('/loyalty-rewards', [LoyaltyRewardController::class, 'index'])->name('loyalty-rewards.index');
        Route::post('/loyalty-rewards', [LoyaltyRewardController::class, 'store'])->name('loyalty-rewards.store');
        Route::patch('/loyalty-rewards/{reward}', [LoyaltyRewardController::class, 'update'])->name('loyalty-rewards.update');
        Route::delete('/loyalty-rewards/{reward}', [LoyaltyRewardController::class, 'destroy'])->name('loyalty-rewards.destroy');

        Route::get('/feedback', [CustomerFeedbackController::class, 'index'])->name('feedback.index');
        Route::post('/feedback', [CustomerFeedbackController::class, 'store'])->name('feedback.store');
        Route::get('/feedback/{feedback}', [CustomerFeedbackController::class, 'show'])->name('feedback.show');
        Route::post('/feedback/{feedback}/replies', [CustomerFeedbackController::class, 'reply'])->name('feedback.replies.store');
        Route::patch('/feedback/{feedback}/status', [CustomerFeedbackController::class, 'updateStatus'])->name('feedback.status.update');
        Route::patch('/feedback/{feedback}/assign', [CustomerFeedbackController::class, 'assign'])->name('feedback.assign');

        Route::get('/campaigns', [MarketingCampaignController::class, 'index'])->name('campaigns.index');
        Route::post('/campaigns', [MarketingCampaignController::class, 'store'])->name('campaigns.store');
        Route::get('/campaigns/preview-audience', [MarketingCampaignController::class, 'previewAudience'])->name('campaigns.preview-audience');
        Route::get('/campaigns/{campaign}', [MarketingCampaignController::class, 'show'])->name('campaigns.show');
        Route::patch('/campaigns/{campaign}', [MarketingCampaignController::class, 'update'])->name('campaigns.update');
        Route::post('/campaigns/{campaign}/send', [MarketingCampaignController::class, 'send'])->name('campaigns.send');
        Route::delete('/campaigns/{campaign}', [MarketingCampaignController::class, 'destroy'])->name('campaigns.destroy');
    });

    Route::prefix('website')->name('website.')->middleware('module:website')->group(function () {
        Route::get('/', [BusinessWebsiteController::class, 'show'])->name('dashboard');
        Route::get('/pages', [BusinessWebsiteController::class, 'pages'])->name('pages');
        Route::patch('/{website}', [BusinessWebsiteController::class, 'updateSettings'])->name('update');
        Route::patch('/{website}/pages/{page}', [BusinessWebsiteController::class, 'updatePage'])->name('pages.update');
        Route::post('/{website}/publish', [BusinessWebsiteController::class, 'publish'])->name('publish');
        Route::post('/{website}/unpublish', [BusinessWebsiteController::class, 'unpublish'])->name('unpublish');

        Route::get('/enquiries', [ProductEnquiryController::class, 'index'])->name('enquiries.index');
        Route::post('/enquiries/{enquiry}/reply', [ProductEnquiryController::class, 'reply'])->name('enquiries.reply');
        Route::patch('/enquiries/{enquiry}/status', [ProductEnquiryController::class, 'updateStatus'])->name('enquiries.status.update');

        Route::get('/blog', [ArticleController::class, 'index'])->name('blog.index');
        Route::get('/blog/create', [ArticleController::class, 'create'])->name('blog.create');
        Route::post('/blog', [ArticleController::class, 'store'])->name('blog.store');
        Route::get('/blog/{article}', [ArticleController::class, 'show'])->name('blog.show');
        Route::post('/blog/{article}', [ArticleController::class, 'update'])->name('blog.update');
        Route::post('/blog/{article}/publish', [ArticleController::class, 'publish'])->name('blog.publish');
        Route::post('/blog/{article}/unpublish', [ArticleController::class, 'unpublish'])->name('blog.unpublish');
        Route::delete('/blog/{article}', [ArticleController::class, 'destroy'])->name('blog.destroy');
    });

    Route::prefix('purchasing')->name('purchasing.')->middleware('module:purchasing')->group(function () {
        Route::get('/', PurchasingDashboardController::class)->name('dashboard');

        Route::get('/orders', [PurchaseOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/create', [PurchaseOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [PurchaseOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [PurchaseOrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/edit', [PurchaseOrderController::class, 'edit'])->name('orders.edit');
        Route::patch('/orders/{order}', [PurchaseOrderController::class, 'update'])->name('orders.update');
        Route::delete('/orders/{order}', [PurchaseOrderController::class, 'destroy'])->name('orders.destroy');
        Route::post('/orders/{order}/duplicate', [PurchaseOrderController::class, 'duplicate'])->name('orders.duplicate');
        Route::post('/orders/{order}/submit', [PurchaseOrderController::class, 'submitForApproval'])->name('orders.submit');
        Route::post('/orders/{order}/approve', [PurchaseOrderController::class, 'approve'])->name('orders.approve');
        Route::post('/orders/{order}/reject', [PurchaseOrderController::class, 'reject'])->name('orders.reject');
        Route::post('/orders/{order}/send', [PurchaseOrderController::class, 'send'])->name('orders.send');
        Route::post('/orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order}/close', [PurchaseOrderController::class, 'close'])->name('orders.close');
        Route::post('/orders/{order}/payments', [PurchaseOrderController::class, 'recordPayment'])->name('orders.payments.store');

        Route::get('/goods-received', [GoodsReceivedNoteController::class, 'index'])->name('goods-received.index');
        Route::get('/orders/{order}/receive', [GoodsReceivedNoteController::class, 'create'])->name('goods-received.create');
        Route::post('/orders/{order}/receive', [GoodsReceivedNoteController::class, 'store'])->name('goods-received.store');
        Route::get('/goods-received/{note}', [GoodsReceivedNoteController::class, 'show'])->name('goods-received.show');
    });

    Route::prefix('pos')->name('pos.')->middleware('module:sales')->group(function () {
        Route::get('/', [POSController::class, 'index'])->name('terminal');
        Route::post('/', [POSController::class, 'store'])->name('checkout');
        Route::get('/{sale}/receipt', [POSController::class, 'receipt'])->name('receipt');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
require __DIR__.'/platform.php';
