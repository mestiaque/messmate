<div class="mb-3">
    <label class="form-label" for="name">Organization name</label>
    <input id="name" name="name" value="{{ old('name', $tenant->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label" for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $tenant->email ?? '') }}" class="form-control @error('email') is-invalid @enderror">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Phone</label>
        <input id="phone" name="phone" value="{{ old('phone', $tenant->phone ?? '') }}" class="form-control @error('phone') is-invalid @enderror">
    </div>
</div>
<div class="mb-3">
    <label class="form-label" for="address">Address</label>
    <textarea id="address" name="address" rows="2" class="form-control">{{ old('address', $tenant->address ?? '') }}</textarea>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <label class="form-label" for="default_currency">Default currency</label>
        <input id="default_currency" name="default_currency" maxlength="3" value="{{ old('default_currency', $tenant->default_currency ?? 'BDT') }}" class="form-control text-uppercase @error('default_currency') is-invalid @enderror" required>
    </div>
    <div class="col-md-8">
        <label class="form-label" for="default_timezone">Default timezone</label>
        <input id="default_timezone" name="default_timezone" value="{{ old('default_timezone', $tenant->default_timezone ?? 'Asia/Dhaka') }}" class="form-control @error('default_timezone') is-invalid @enderror" required>
    </div>
</div>
