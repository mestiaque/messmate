{{-- Action button posting to a decide route; actions other than approve prompt for a reason. --}}
<form method="POST" action="{{ $action }}" class="d-inline"
      @if ($value !== 'approve') onsubmit="const r = prompt('{{ $prompt ?? 'Reason?' }}'); if (! r) return false; this.reason.value = r;" @endif>
    @csrf
    <input type="hidden" name="action" value="{{ $value }}">
    <input type="hidden" name="reason">
    <button class="btn btn-sm {{ $class ?? 'btn-outline-secondary' }}">{{ $label }}</button>
</form>
