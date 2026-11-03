<form method="GET" class="d-flex flex-wrap gap-2">
    <select name="period" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto" aria-label="Period">
        <option value="">All periods</option>
        @foreach ($periods as $p)<option value="{{ $p->id }}" @selected(request('period') == $p->id)>{{ $p->name }}</option>@endforeach
    </select>
    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto" aria-label="Status">
        <option value="">All statuses</option>
        @foreach ($statuses as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
</form>
