<div class="mb-3">
    <label class="form-label" for="name">Mess name</label>
    <input id="name" name="name" value="{{ old('name', $mess->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <textarea id="description" name="description" rows="2" class="form-control">{{ old('description', $mess->description ?? '') }}</textarea>
</div>
<div class="mb-3">
    <label class="form-label" for="address">Address</label>
    <textarea id="address" name="address" rows="2" class="form-control">{{ old('address', $mess->address ?? '') }}</textarea>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label" for="contact_phone">Contact phone</label>
        <input id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $mess->contact_phone ?? '') }}" class="form-control">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="contact_email">Contact email</label>
        <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $mess->contact_email ?? '') }}" class="form-control @error('contact_email') is-invalid @enderror">
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <label class="form-label" for="currency">Currency</label>
        <input id="currency" name="currency" maxlength="3" value="{{ old('currency', $mess->currency ?? $tenant->default_currency) }}" class="form-control text-uppercase" required>
    </div>
    <div class="col-md-9">
        <label class="form-label" for="timezone">Timezone</label>
        <input id="timezone" name="timezone" value="{{ old('timezone', $mess->timezone ?? $tenant->default_timezone) }}" class="form-control @error('timezone') is-invalid @enderror" required>
    </div>
</div>
@php($periodMode = old('default_period_mode', isset($mess) ? $mess->default_period_mode->value : 'calendar'))
@php($trackingMode = old('default_meal_tracking_mode', isset($mess) ? $mess->default_meal_tracking_mode->value : 'individual'))
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label" for="default_period_mode">Accounting period</label>
        <select id="default_period_mode" name="default_period_mode" class="form-select">
            <option value="calendar" @selected($periodMode === 'calendar')>Calendar month</option>
            <option value="custom" @selected($periodMode === 'custom')>Custom date range</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="default_meal_tracking_mode">Meal tracking</label>
        <select id="default_meal_tracking_mode" name="default_meal_tracking_mode" class="form-select">
            <option value="individual" @selected($trackingMode === 'individual')>Individual meals (breakfast / lunch / dinner)</option>
            <option value="daily_total" @selected($trackingMode === 'daily_total')>Daily total units</option>
        </select>
    </div>
</div>
