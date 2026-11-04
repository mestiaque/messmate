@extends('layouts.app')
@section('title', 'Plan & billing')
@section('content')
    @php($fmt = fn ($v) => number_format((float) $v, 2))
    @php($plan = $subscription->plan)
    <h1 class="h4 mb-3">Plan & billing</h1>
    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="text-muted small">Current plan</div><div class="fs-4 fw-semibold">{{ $plan->name }}</div></div>
                    <span class="badge text-bg-{{ $subscription->status->badge() }}">{{ str_replace('_', ' ', $subscription->status->value) }}</span>
                </div>
                <dl class="small mb-0 mt-2">
                    @if ($subscription->trial_ends_at && $subscription->status->value === 'trial')<dt>Trial ends</dt><dd>{{ $subscription->trial_ends_at->format('d M Y') }}</dd>@endif
                    @if ($subscription->current_period_end)<dt>{{ $subscription->status->value === 'cancelled' ? 'Access until' : 'Paid until' }}</dt><dd>{{ $subscription->current_period_end->format('d M Y') }} ({{ $subscription->billing_cycle }})</dd>@endif
                    @if ($subscription->grace_ends_at)<dt>Grace period ends</dt><dd>{{ $subscription->grace_ends_at->format('d M Y') }}</dd>@endif
                    <dt>Access</dt><dd class="{{ $access === 'full' ? 'text-success' : 'text-danger' }}">{{ $access === 'full' ? 'Full' : 'Read-only — data is kept; renew to edit' }}</dd>
                </dl>
                @if (in_array($subscription->status->value, ['active', 'past_due']) && $subscription->current_period_end)
                    <form method="POST" action="{{ route('billing.cancel') }}" onsubmit="return confirm('Cancel at the end of the paid period?')" class="mt-2">@csrf
                        <button class="btn btn-sm btn-outline-danger">Cancel subscription</button></form>
                @endif
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Usage</div>
                <table class="table table-sm mb-0">
                    @foreach ([['Messes', $usage['messes'], $plan->max_messes], ['Organization users', $usage['users'], $plan->max_users], ['Largest mess (members)', $usage['members'] ? max($usage['members']) : 0, $plan->max_members_per_mess]] as [$label, $used, $limit])
                        <tr><td>{{ $label }}</td><td class="text-end" style="width: 40%">
                            {{ $used }} / {{ $limit ?? '∞' }}
                            @if ($limit)<div class="progress mt-1" style="height: 4px" role="progressbar" aria-label="{{ $label }} usage" aria-valuenow="{{ $used }}" aria-valuemax="{{ $limit }}"><div class="progress-bar {{ $used >= $limit ? 'bg-danger' : '' }}" style="width: {{ min(100, $used / $limit * 100) }}%"></div></div>@endif
                        </td></tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>

    <h2 class="h5">Choose a plan</h2>
    <div class="row g-3 mb-4">
        @foreach ($plans as $p)
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 {{ $p->id === $plan->id ? 'border border-primary' : '' }}">
                    <div class="card-body d-flex flex-column">
                        <div class="fw-semibold">{{ $p->name }} @if ($p->id === $plan->id)<span class="badge text-bg-primary">current</span>@endif</div>
                        <div class="fs-5">{{ $p->isFree() ? 'Free' : $p->currency.' '.$fmt($p->monthly_price).'/mo' }}</div>
                        @unless ($p->isFree())<div class="small text-muted">or {{ $fmt($p->yearly_price) }}/year</div>@endunless
                        <ul class="small ps-3 my-2">
                            <li>{{ $p->max_messes ?? 'Unlimited' }} mess(es)</li>
                            <li>{{ $p->max_members_per_mess ?? 'Unlimited' }} members per mess</li>
                            <li>{{ $p->max_users ?? 'Unlimited' }} users</li>
                            @foreach ($p->features ?? [] as $f)<li>{{ \Database\Seeders\PlanSeeder::FEATURES[$f] ?? $f }}</li>@endforeach
                        </ul>
                        <form method="POST" action="{{ route('billing.checkout') }}" class="mt-auto">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $p->id }}">
                            @if ($p->isFree())
                                <input type="hidden" name="billing_cycle" value="monthly"><input type="hidden" name="provider" value="manual">
                                <button class="btn btn-outline-secondary w-100" @disabled($p->id === $plan->id)>Switch to Free</button>
                            @else
                                <select name="billing_cycle" class="form-select form-select-sm mb-2" aria-label="Billing cycle">
                                    <option value="monthly">Monthly — {{ $fmt($p->monthly_price) }}</option>
                                    <option value="yearly">Yearly — {{ $fmt($p->yearly_price) }}</option>
                                </select>
                                <select name="provider" class="form-select form-select-sm mb-2 js-provider" aria-label="Payment method">
                                    @foreach ($providers as $key => $provider)<option value="{{ $key }}">{{ $provider->label() }}</option>@endforeach
                                </select>
                                <input name="transaction_reference" class="form-control form-control-sm mb-2 js-ref" placeholder="Transfer / bKash transaction ID" aria-label="Transaction reference">
                                <button class="btn btn-primary w-100">{{ $p->id !== $plan->id ? 'Choose '.$p->name : ($subscription->status->value === 'trial' ? 'Subscribe' : 'Renew') }}</button>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @if (array_key_exists('manual', $providers))
        <p class="small text-muted">Bank / bKash: {{ \App\Models\PlatformSetting::get('payment_instructions', 'Payment instructions are provided by the platform administrator.') }}</p>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Invoices</div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    @forelse ($invoices as $inv)
                        <tr><td><a href="{{ route('billing.invoices.show', ['invoice' => $inv]) }}">{{ $inv->invoice_number }}</a></td><td class="small">{{ $inv->description }}</td>
                            <td class="text-end">{{ $inv->currency }} {{ $fmt($inv->total) }}</td>
                            <td><span class="badge text-bg-{{ $inv->status === 'paid' ? 'success' : 'warning' }}">{{ $inv->status }}</span>
                                @if ($inv->status !== 'paid' && $inv->payments->last()?->status === 'pending')<span class="small text-muted">awaiting confirmation</span>@endif
                                @if ($inv->payments->last()?->status === 'failed')<span class="small text-danger">payment failed</span>@endif</td></tr>
                    @empty
                        <tr><td class="text-muted small p-3">No invoices yet.</td></tr>
                    @endforelse
                </table></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">History</div>
                <ul class="list-group list-group-flush small">
                    @foreach ($events as $e)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ str_replace('_', ' ', $e->event) }} {{ isset($e->data['to']) ? '→ '.$e->data['to'] : '' }}</span><span class="text-muted">{{ $e->created_at->format('d M Y') }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('.js-provider').forEach(sel => {
            const ref = sel.closest('form').querySelector('.js-ref');
            const sync = () => { ref.classList.toggle('d-none', sel.value !== 'manual'); ref.required = sel.value === 'manual'; };
            sel.addEventListener('change', sync); sync();
        });
    </script>
@endsection
