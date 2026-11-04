@php($f = $plan ? 'plan-'.$plan->id : 'plan-new')
<form method="POST" action="{{ $plan ? route('platform.plans.update', $plan) : route('platform.plans.store') }}" class="card-body">
    @csrf @if ($plan) @method('PUT') @endif
    <input type="hidden" name="plan_id" value="{{ $plan?->id }}">
    <div class="row g-2">
        <div class="col-7"><label class="form-label" for="{{ $f }}-name">Name</label><input id="{{ $f }}-name" name="name" value="{{ $plan?->name }}" class="form-control form-control-sm" required></div>
        <div class="col-5"><label class="form-label" for="{{ $f }}-slug">Slug</label><input id="{{ $f }}-slug" name="slug" value="{{ $plan?->slug }}" class="form-control form-control-sm" required></div>
        <div class="col-6"><label class="form-label" for="{{ $f }}-m">Monthly price</label><input id="{{ $f }}-m" name="monthly_price" value="{{ $plan?->monthly_price ?? 0 }}" class="form-control form-control-sm" inputmode="decimal" required></div>
        <div class="col-6"><label class="form-label" for="{{ $f }}-y">Yearly price</label><input id="{{ $f }}-y" name="yearly_price" value="{{ $plan?->yearly_price ?? 0 }}" class="form-control form-control-sm" inputmode="decimal" required></div>
        <div class="col-4"><label class="form-label" for="{{ $f }}-mx">Messes</label><input id="{{ $f }}-mx" name="max_messes" value="{{ $plan?->max_messes }}" class="form-control form-control-sm" placeholder="∞"></div>
        <div class="col-4"><label class="form-label" for="{{ $f }}-mm">Members</label><input id="{{ $f }}-mm" name="max_members_per_mess" value="{{ $plan?->max_members_per_mess }}" class="form-control form-control-sm" placeholder="∞"></div>
        <div class="col-4"><label class="form-label" for="{{ $f }}-mu">Users</label><input id="{{ $f }}-mu" name="max_users" value="{{ $plan?->max_users }}" class="form-control form-control-sm" placeholder="∞"></div>
        <div class="col-6"><label class="form-label" for="{{ $f }}-t">Trial days</label><input id="{{ $f }}-t" name="trial_days" value="{{ $plan?->trial_days ?? 0 }}" class="form-control form-control-sm" required></div>
        <div class="col-6"><label class="form-label" for="{{ $f }}-o">Display order</label><input id="{{ $f }}-o" name="sort_order" value="{{ $plan?->sort_order ?? 0 }}" class="form-control form-control-sm"></div>
        <div class="col-12"><label class="form-label" for="{{ $f }}-d">Description</label><input id="{{ $f }}-d" name="description" value="{{ $plan?->description }}" class="form-control form-control-sm"></div>
        <div class="col-12">
            @foreach ($features as $key => $label)
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="features[]" value="{{ $key }}" id="{{ $f }}-{{ $key }}" @checked($plan?->hasFeature($key))><label class="form-check-label small" for="{{ $f }}-{{ $key }}">{{ $label }}</label></div>
            @endforeach
            <input type="hidden" name="is_active" value="0">
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="{{ $f }}-active" @checked($plan?->is_active ?? true)><label class="form-check-label small" for="{{ $f }}-active">Available to new customers</label></div>
        </div>
    </div>
    <p class="form-text mb-2">Leave a limit empty for unlimited.</p>
    <button class="btn btn-sm btn-primary w-100">{{ $plan ? 'Save changes' : 'Create plan' }}</button>
</form>
