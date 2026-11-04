<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\PaymentCallbackController;
use App\Http\Controllers\Billing\SandboxGatewayController;
use App\Http\Controllers\Billing\SupportController;
use App\Http\Controllers\Finance\BazarController;
use App\Http\Controllers\Finance\DepositController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\FinanceReportController;
use App\Http\Controllers\Finance\FinanceSettingsController;
use App\Http\Controllers\Finance\LedgerController;
use App\Http\Controllers\Finance\ReceiptController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Meals\ApprovalController;
use App\Http\Controllers\Meals\GuestMealController;
use App\Http\Controllers\Meals\MealAdjustmentController;
use App\Http\Controllers\Meals\MealEntryController;
use App\Http\Controllers\Meals\MealTypeController;
use App\Http\Controllers\Meals\PeriodController;
use App\Http\Controllers\Mess\MemberController;
use App\Http\Controllers\Mess\MessController;
use App\Http\Controllers\Platform\PlatformBillingController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\Platform\PlatformSupportController;
use App\Http\Controllers\Portal\AnnouncementController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Settlement\FinancialAdjustmentController;
use App\Http\Controllers\Settlement\SettlementController;
use App\Http\Controllers\Settlement\SettlementPaymentController;
use App\Http\Controllers\Tenant\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');
});

// Server-to-server payment callbacks: no session, no CSRF; trust comes from the provider signature.
Route::post('billing/callback/{provider}', PaymentCallbackController::class)->middleware('throttle:60,1')->name('billing.callback');

Route::middleware('auth')->group(function () {
    Route::get('billing/sandbox/{reference}', [SandboxGatewayController::class, 'show'])->name('billing.sandbox.show');
    Route::post('billing/sandbox/{reference}', [SandboxGatewayController::class, 'pay'])->name('billing.sandbox.pay');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/', HomeController::class)->name('home');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('invitations.accept');

    Route::get('organizations/create', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('organizations', [TenantController::class, 'store'])->name('tenants.store');

    Route::prefix('t/{tenant}')->middleware(['tenant', 'subscription.writable'])->scopeBindings()->group(function () {
        Route::get('/', [TenantController::class, 'dashboard'])->name('tenant.dashboard');
        Route::get('settings', [TenantController::class, 'edit'])->name('tenant.settings.edit');
        Route::put('settings', [TenantController::class, 'update'])->name('tenant.settings.update');
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('billing/checkout', [BillingController::class, 'checkout'])->middleware('throttle:10,1')->name('billing.checkout');
        Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
        Route::get('billing/invoices/{invoice}', [BillingController::class, 'invoice'])->name('billing.invoices.show');
        Route::get('support', [SupportController::class, 'index'])->name('support.index');
        Route::post('support', [SupportController::class, 'store'])->name('support.store');
        Route::get('support/{supportTicket}', [SupportController::class, 'show'])->name('support.show');
        Route::post('support/{supportTicket}/reply', [SupportController::class, 'reply'])->name('support.reply');
        Route::get('messes/create', [MessController::class, 'create'])->name('messes.create');
        Route::post('messes', [MessController::class, 'store'])->name('messes.store');

        Route::prefix('m/{mess}')->middleware('mess')->group(function () {
            Route::get('/', [MessController::class, 'dashboard'])->name('mess.dashboard');
            Route::get('settings', [MessController::class, 'edit'])->name('mess.edit');
            Route::put('settings', [MessController::class, 'update'])->name('mess.update');

            Route::get('members', [MemberController::class, 'index'])->name('members.index');
            Route::post('members', [MemberController::class, 'store'])->name('members.store');
            Route::get('members/create', [MemberController::class, 'create'])->name('members.create');
            Route::post('members/without-account', [MemberController::class, 'storeWithoutAccount'])->name('members.store-manual');
            Route::post('members/invitations', [MemberController::class, 'invite'])->name('members.invite');
            Route::delete('members/invitations/{invitation}', [MemberController::class, 'revokeInvitation'])->name('members.invitations.revoke');
            Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show');
            Route::get('members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
            Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');
            Route::put('members/{member}/status', [MemberController::class, 'changeStatus'])->name('members.status');
            Route::put('members/{member}/link', [MemberController::class, 'linkUser'])->name('members.link');
            Route::put('members/{member}/roles', [MemberController::class, 'syncRoles'])->name('members.roles.sync');

            Route::get('periods', [PeriodController::class, 'index'])->name('periods.index');
            Route::post('periods', [PeriodController::class, 'store'])->name('periods.store');
            Route::put('periods/{period}/status', [PeriodController::class, 'transition'])->name('periods.transition');
            Route::post('periods/{period}/reopen', [PeriodController::class, 'reopen'])->name('periods.reopen');

            Route::get('meal-types', [MealTypeController::class, 'index'])->name('meal-types.index');
            Route::post('meal-types', [MealTypeController::class, 'store'])->name('meal-types.store');
            Route::put('meal-types/{mealType}', [MealTypeController::class, 'update'])->name('meal-types.update');

            Route::get('meals/daily', [MealEntryController::class, 'daily'])->name('meals.daily');
            Route::post('meals/daily', [MealEntryController::class, 'storeDaily'])->name('meals.daily.store');
            Route::get('meals/calendar', [MealEntryController::class, 'calendar'])->name('meals.calendar');
            Route::get('meals/guests', [GuestMealController::class, 'index'])->name('meals.guests');
            Route::post('meals/guests', [GuestMealController::class, 'store'])->name('meals.guests.store');
            Route::get('meals/adjustments', [MealAdjustmentController::class, 'index'])->name('meals.adjustments');
            Route::post('meals/adjustments', [MealAdjustmentController::class, 'store'])->name('meals.adjustments.store');
            Route::get('meals/approvals', [ApprovalController::class, 'index'])->name('meals.approvals');
            Route::post('meals/approvals/bulk', [ApprovalController::class, 'bulkApprove'])->name('meals.approvals.bulk');
            Route::post('meals/approvals/{type}/{id}', [ApprovalController::class, 'decide'])
                ->whereIn('type', ['meal-entries', 'guest-meals', 'adjustments'])->whereNumber('id')->name('meals.approvals.decide');

            Route::get('bazar', [BazarController::class, 'index'])->name('bazar.index');
            Route::get('bazar/create', [BazarController::class, 'create'])->name('bazar.create');
            Route::post('bazar', [BazarController::class, 'store'])->name('bazar.store');
            Route::get('bazar/{bazarEntry}', [BazarController::class, 'show'])->name('bazar.show');
            Route::get('bazar/{bazarEntry}/edit', [BazarController::class, 'edit'])->name('bazar.edit');
            Route::put('bazar/{bazarEntry}', [BazarController::class, 'update'])->name('bazar.update');
            Route::post('bazar/{bazarEntry}/decide', [BazarController::class, 'decide'])->name('bazar.decide');

            Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
            Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
            Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
            Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
            Route::post('expenses/{expense}/decide', [ExpenseController::class, 'decide'])->name('expenses.decide');

            Route::get('deposits', [DepositController::class, 'index'])->name('deposits.index');
            Route::post('deposits', [DepositController::class, 'store'])->name('deposits.store');
            Route::post('deposits/{deposit}/decide', [DepositController::class, 'decide'])->name('deposits.decide');

            Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');
            Route::get('ledger/balances', [LedgerController::class, 'balances'])->name('ledger.balances');
            Route::get('finance/report', FinanceReportController::class)->name('finance.report');
            Route::get('finance/settings', [FinanceSettingsController::class, 'index'])->name('finance.settings');
            Route::post('finance/bazar-categories', [FinanceSettingsController::class, 'storeBazarCategory'])->name('finance.bazar-categories.store');
            Route::put('finance/bazar-categories/{bazarCategory}', [FinanceSettingsController::class, 'updateBazarCategory'])->name('finance.bazar-categories.update');
            Route::post('finance/expense-categories', [FinanceSettingsController::class, 'storeExpenseCategory'])->name('finance.expense-categories.store');
            Route::put('finance/expense-categories/{expenseCategory}', [FinanceSettingsController::class, 'updateExpenseCategory'])->name('finance.expense-categories.update');
            Route::get('settlements', [SettlementController::class, 'index'])->name('settlements.index');
            Route::put('settlements/settings', [SettlementController::class, 'updateSettings'])->name('settlements.settings');
            Route::post('settlements/draft', [SettlementController::class, 'draft'])->name('settlements.draft');
            Route::post('settlements/finalize', [SettlementController::class, 'finalize'])->name('settlements.finalize');
            Route::get('settlements/{settlement}', [SettlementController::class, 'show'])->name('settlements.show');
            Route::post('settlements/{settlement}/payments', [SettlementPaymentController::class, 'store'])->name('settlements.payments.store');
            Route::get('finance/adjustments', [FinancialAdjustmentController::class, 'index'])->name('financial-adjustments.index');
            Route::post('finance/adjustments', [FinancialAdjustmentController::class, 'store'])->name('financial-adjustments.store');
            Route::post('finance/adjustments/{financialAdjustment}/decide', [FinancialAdjustmentController::class, 'decide'])->name('financial-adjustments.decide');
            Route::get('me', [PortalController::class, 'index'])->name('portal.index');
            Route::get('me/meals', [PortalController::class, 'meals'])->name('portal.meals');
            Route::post('me/meals', [PortalController::class, 'storeMeals'])->middleware('throttle:30,1')->name('portal.meals.store');
            Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
            Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
            Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
            Route::put('portal-settings', [AnnouncementController::class, 'settings'])->name('portal.settings');
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports.show');
            Route::get('receipts/{type}/{id}', [ReceiptController::class, 'show'])->whereIn('type', ['bazar', 'expense', 'deposit'])->whereNumber('id')->name('receipts.show');
        });
    });

    Route::prefix('platform')->name('platform.')->middleware('platform.admin')->group(function () {
        Route::get('/', [PlatformController::class, 'dashboard'])->name('dashboard');
        Route::get('tenants', [PlatformController::class, 'tenants'])->name('tenants.index');
        Route::get('tenants/{tenant:id}', [PlatformController::class, 'showTenant'])->name('tenants.show');
        Route::post('tenants/{tenant:id}/suspend', [PlatformController::class, 'suspendTenant'])->name('tenants.suspend');
        Route::post('tenants/{tenant:id}/activate', [PlatformController::class, 'activateTenant'])->name('tenants.activate');
        Route::put('tenants/{tenant:id}/subscription', [PlatformBillingController::class, 'setSubscription'])->name('tenants.subscription');
        Route::get('plans', [PlatformBillingController::class, 'plans'])->name('plans.index');
        Route::post('plans', [PlatformBillingController::class, 'storePlan'])->name('plans.store');
        Route::put('plans/{plan}', [PlatformBillingController::class, 'updatePlan'])->name('plans.update');
        Route::get('payments', [PlatformBillingController::class, 'payments'])->name('payments.index');
        Route::post('payments/{payment}/decide', [PlatformBillingController::class, 'decidePayment'])->name('payments.decide');
        Route::get('settings', [PlatformBillingController::class, 'settings'])->name('settings');
        Route::put('settings', [PlatformBillingController::class, 'updateSettings'])->name('settings.update');
        Route::get('tickets', [PlatformSupportController::class, 'tickets'])->name('tickets.index');
        Route::get('tickets/{ticket}', [PlatformSupportController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [PlatformSupportController::class, 'reply'])->name('tickets.reply');
        Route::get('announcements', [PlatformSupportController::class, 'announcements'])->name('announcements.index');
        Route::post('announcements', [PlatformSupportController::class, 'storeAnnouncement'])->name('announcements.store');
        Route::delete('announcements/{announcement}', [PlatformSupportController::class, 'withdrawAnnouncement'])->name('announcements.withdraw');
    });
});
