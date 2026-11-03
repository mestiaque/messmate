<?php

namespace App\Http\Controllers\Finance;

use App\Enums\ExpenseCategoryType;
use App\Http\Controllers\Controller;
use App\Models\BazarCategory;
use App\Models\ExpenseCategory;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\FinanceSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bazar and expense categories. Categories are deactivated, never deleted,
 * because historical records reference them.
 */
class FinanceSettingsController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Tenant $tenant, Mess $mess, FinanceSetupService $setup): View
    {
        $this->authorize('manageFinanceSettings', $mess);
        $setup->ensureDefaults($mess);

        return view('finance.settings', [
            'mess' => $mess,
            'bazarCategories' => $mess->bazarCategories()->orderBy('name')->get(),
            'expenseCategories' => $mess->expenseCategories()->orderBy('name')->get(),
        ]);
    }

    public function storeBazarCategory(Request $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $this->authorize('manageFinanceSettings', $mess);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('bazar_categories')->where('mess_id', $mess->id)],
            'is_food_related' => ['boolean'],
        ]);

        $category = $mess->bazarCategories()->create(['tenant_id' => $mess->tenant_id, 'name' => $data['name'], 'is_food_related' => $request->boolean('is_food_related')]);
        $this->audit->log('bazar_category.created', $category, new: $category->only(['name', 'is_food_related']));

        return back()->with('status', 'Bazar category added.');
    }

    public function updateBazarCategory(Request $request, Tenant $tenant, Mess $mess, BazarCategory $bazarCategory): RedirectResponse
    {
        $this->authorize('manageFinanceSettings', $mess);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('bazar_categories')->where('mess_id', $mess->id)->ignore($bazarCategory)],
        ]);

        $old = $bazarCategory->only(['name', 'is_food_related', 'is_active']);
        $bazarCategory->update(['name' => $data['name'], 'is_food_related' => $request->boolean('is_food_related'), 'is_active' => $request->boolean('is_active')]);
        $this->audit->log('bazar_category.updated', $bazarCategory, $old, $bazarCategory->only(['name', 'is_food_related', 'is_active']));

        return back()->with('status', 'Bazar category saved. Already-saved purchases keep their computed food amount.');
    }

    public function storeExpenseCategory(Request $request, Tenant $tenant, Mess $mess): RedirectResponse
    {
        $this->authorize('manageFinanceSettings', $mess);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories')->where('mess_id', $mess->id)],
            'category_type' => ['required', Rule::enum(ExpenseCategoryType::class)],
        ]);

        $category = $mess->expenseCategories()->create(['tenant_id' => $mess->tenant_id] + $data);
        $this->audit->log('expense_category.created', $category, new: $data);

        return back()->with('status', 'Expense category added.');
    }

    public function updateExpenseCategory(Request $request, Tenant $tenant, Mess $mess, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('manageFinanceSettings', $mess);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories')->where('mess_id', $mess->id)->ignore($expenseCategory)],
            'category_type' => ['required', Rule::enum(ExpenseCategoryType::class)],
        ]);

        $old = $expenseCategory->only(['name', 'category_type', 'is_active']);
        $expenseCategory->update($data + ['is_active' => $request->boolean('is_active')]);
        $this->audit->log('expense_category.updated', $expenseCategory, $old, $expenseCategory->only(['name', 'category_type', 'is_active']));

        return back()->with('status', 'Expense category saved.');
    }
}
