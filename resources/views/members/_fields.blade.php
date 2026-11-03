<div class="mb-3">
    <label class="form-label" for="full_name">Full name</label>
    <input id="full_name" name="full_name" value="{{ old('full_name', $member->full_name ?? '') }}" class="form-control @error('full_name') is-invalid @enderror" required>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-6"><label class="form-label" for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $member->phone ?? '') }}" class="form-control"></div>
    <div class="col-md-6"><label class="form-label" for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $member->email ?? '') }}" class="form-control @error('email') is-invalid @enderror"></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-6"><label class="form-label" for="room_number">Room</label><input id="room_number" name="room_number" value="{{ old('room_number', $member->room_number ?? '') }}" class="form-control"></div>
    <div class="col-md-6"><label class="form-label" for="joined_at">Joined on</label><input id="joined_at" type="date" name="joined_at" value="{{ old('joined_at', isset($member) ? $member->joined_at->toDateString() : now($mess->timezone)->toDateString()) }}" class="form-control @error('joined_at') is-invalid @enderror" required></div>
</div>
<div class="mb-3"><label class="form-label" for="notes">Notes</label><textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes', $member->notes ?? '') }}</textarea></div>
