<?php

namespace App\Http\Controllers\Finance;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\BazarRequest;
use App\Models\BazarEntry;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Finance\BazarService;
use App\Services\Finance\FinanceSetupService;
use App\Services\Finance\IdempotencyService;
use App\Services\Finance\ReceiptStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BazarController extends Controller
{
    public function __construct(
        private readonly BazarService $bazar,
        private readonly ReceiptStorage $receipts,
    ) {}

    public function index(Request $request, Tenant $tenant, Mess $mess): View
    {
        $this->authorizeAny($request, $mess);

        $entries = $mess->bazarEntries()
            ->with('purchaser:id,full_name', 'category:id,name', 'period:id,name,status')
            ->when($request->query('period'), fn ($q, $p) => $q->where('period_id', $p))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('purchase_date')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('finance.bazar.index', [
            'mess' => $mess,
            'entries' => $entries,
            'periods' => $mess->periods()->orderByDesc('start_date')->get(['id', 'name']),
            'canManage' => $request->user()->can('manageBazar', $mess),
            'canApprove' => $request->user()->can('approveBazar', $mess),
        ]);
    }

    public function create(Request $request, Tenant $tenant, Mess $mess, FinanceSetupService $setup): View
    {
        $this->authorize('manageBazar', $mess);
        $setup->ensureDefaults($mess);

        return view('finance.bazar.form', $this->formData($mess, new BazarEntry([
            'purchase_date' => now($mess->timezone), 'entry_mode' => 'itemized', 'payment_method' => PaymentMethod::Cash,
            'purchased_by' => $request->user()->activeMembershipIn($mess)?->id,
        ])));
    }

    public function store(BazarRequest $request, Tenant $tenant, Mess $mess, IdempotencyService $idempotency): RedirectResponse
    {
        [$fresh, $entry] = $idempotency->once($mess->tenant_id, $request->input('idempotency_key'), $request->safe()->except('receipt'), fn () => $this->bazar->save(
            $mess, $request->safe()->except(['receipt', 'idempotency_key']), $request->user(), receiptPath: $this->receipts->store($mess, $request->file('receipt')),
        ));

        if (! $fresh) {
            return redirect()->route('bazar.index')->with('status', 'That purchase was already saved.');
        }

        return redirect()->route('bazar.show', ['bazarEntry' => $entry])->with('status', 'Purchase recorded: '.number_format($entry->total_amount, 2).'.');
    }

    public function show(Request $request, Tenant $tenant, Mess $mess, BazarEntry $bazarEntry): View
    {
        $this->authorizeAny($request, $mess);

        return view('finance.bazar.show', [
            'mess' => $mess,
            'entry' => $bazarEntry->load('items.category', 'purchaser', 'category', 'period', 'ledgerTransaction.lines.account', 'creator'),
            'canManage' => $request->user()->can('manageBazar', $mess),
            'canApprove' => $request->user()->can('approveBazar', $mess),
        ]);
    }

    public function edit(Tenant $tenant, Mess $mess, BazarEntry $bazarEntry): View|RedirectResponse
    {
        $this->authorize('manageBazar', $mess);

        if (! $bazarEntry->isEditable()) {
            return redirect()->route('bazar.show', ['bazarEntry' => $bazarEntry])->withErrors(['status' => 'Approved or voided purchases cannot be edited.']);
        }

        return view('finance.bazar.form', $this->formData($mess, $bazarEntry->load('items')));
    }

    public function update(BazarRequest $request, Tenant $tenant, Mess $mess, BazarEntry $bazarEntry): RedirectResponse
    {
        $this->bazar->save($mess, $request->safe()->except(['receipt', 'idempotency_key']), $request->user(), $bazarEntry, $this->receipts->store($mess, $request->file('receipt')));

        return redirect()->route('bazar.show', ['bazarEntry' => $bazarEntry])->with('status', 'Purchase updated and resubmitted.');
    }

    public function decide(Request $request, Tenant $tenant, Mess $mess, BazarEntry $bazarEntry): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'void'])],
            'reason' => ['required_unless:action,approve', 'nullable', 'string', 'max:255'],
        ]);
        $this->authorize($data['action'] === 'void' ? 'manageBazar' : 'approveBazar', $mess);

        match ($data['action']) {
            'approve' => $this->bazar->approve($bazarEntry, $request->user()),
            'reject' => $this->bazar->reject($bazarEntry, $request->user(), $data['reason']),
            'void' => $this->bazar->void($bazarEntry, $request->user(), $data['reason']),
        };

        return back()->with('status', 'Purchase '.['approve' => 'approved and posted to the ledger', 'reject' => 'rejected', 'void' => 'voided'][$data['action']].'.');
    }

    private function formData(Mess $mess, BazarEntry $entry): array
    {
        return [
            'mess' => $mess,
            'entry' => $entry,
            // Food categories first so a new item row never defaults to a non-food category.
            'categories' => $mess->bazarCategories()->where('is_active', true)->orderByDesc('is_food_related')->orderBy('name')->get(),
            'members' => $mess->members()->whereIn('membership_status', ['active', 'left'])->orderBy('member_code')->get(['id', 'member_code', 'full_name']),
            'idempotencyKey' => (string) Str::uuid(),
        ];
    }

    private function authorizeAny(Request $request, Mess $mess): void
    {
        $user = $request->user();
        abort_unless($user->can('manageBazar', $mess) || $user->can('approveBazar', $mess) || $user->can('viewFinance', $mess), 403);
    }
}
