<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\BillingService;
use App\Services\Billing\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformBillingController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function plans(): View
    {
        return view('platform.plans', ['plans' => SubscriptionPlan::query()->withCount('subscriptions')->orderBy('sort_order')->get(), 'features' => PlanSeeder::FEATURES]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $plan = SubscriptionPlan::create($this->planData($request) + ['currency' => 'BDT']);
        $this->audit->log('plan.created', $plan, new: $plan->only(['slug', 'monthly_price']));

        return back()->with('status', "Plan {$plan->name} created.");
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $old = $plan->only(['monthly_price', 'yearly_price', 'max_messes', 'max_members_per_mess', 'max_users', 'is_active']);
        $plan->update($this->planData($request, $plan));
        $this->audit->log('plan.updated', $plan, $old, $plan->only(array_keys($old)));

        return back()->with('status', "Plan {$plan->name} saved. Existing subscriptions keep running; new limits apply immediately.");
    }

    public function payments(Request $request): View
    {
        return view('platform.payments', [
            'payments' => SubscriptionPayment::query()->with('tenant:id,name', 'invoice:id,invoice_number,description')
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->latest('id')->paginate(30)->withQueryString(),
            'invoices' => Invoice::query()->with('tenant:id,name')->latest('id')->limit(20)->get(),
        ]);
    }

    public function decidePayment(Request $request, SubscriptionPayment $payment, BillingService $billing): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['confirm', 'reject'])], 'reason' => ['required_if:action,reject', 'nullable', 'string', 'max:255']]);

        $data['action'] === 'confirm'
            ? $billing->confirmManual($payment, $request->user())
            : $billing->rejectManual($payment, $request->user(), $data['reason']);

        return back()->with('status', $data['action'] === 'confirm' ? 'Payment confirmed; the subscription is active.' : 'Payment rejected.');
    }

    public function setSubscription(Request $request, Tenant $tenant, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'integer', 'exists:subscription_plans,id'], 'until' => ['nullable', 'date', 'after:today']]);

        $subscriptions->adminSet($tenant, SubscriptionPlan::findOrFail($data['plan_id']), isset($data['until']) ? Carbon::parse($data['until'])->endOfDay() : null, $request->user());

        return back()->with('status', 'Subscription updated.');
    }

    public function settings(): View
    {
        return view('platform.settings', [
            'graceDays' => PlatformSetting::get('grace_days', config('billing.default_grace_days')),
            'trialPlan' => PlatformSetting::get('default_trial_plan', config('billing.default_trial_plan')),
            'instructions' => PlatformSetting::get('payment_instructions', ''),
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get(['slug', 'name']),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'default_trial_plan' => ['required', 'exists:subscription_plans,slug'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($data as $key => $value) {
            PlatformSetting::put($key, $key === 'grace_days' ? (int) $value : $value);
        }
        $this->audit->log('platform_settings.updated', null, new: $data);

        return back()->with('status', 'Platform settings saved.');
    }

    private function planData(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash', 'max:50', Rule::unique('subscription_plans', 'slug')->ignore($plan)],
            'description' => ['nullable', 'string', 'max:500'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:9999999', 'decimal:0,2'],
            'max_messes' => ['nullable', 'integer', 'min:1'],
            'max_members_per_mess' => ['nullable', 'integer', 'min:1'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'features' => ['array'],
            'features.*' => [Rule::in(array_keys(PlanSeeder::FEATURES))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return ['features' => array_values($data['features'] ?? []), 'is_active' => $request->boolean('is_active')] + $data;
    }
}
