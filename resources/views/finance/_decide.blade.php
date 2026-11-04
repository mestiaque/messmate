{{-- Action button posting to a decide route; reject/void/reverse (or an explicit $prompt) ask for a reason. --}}
<form method="POST" action="{{ $action }}" class="d-inline"
      @if (isset($prompt) || in_array($value, ['reject', 'void', 'reverse'], true)) onsubmit="const r = prompt('{{ $prompt ?? 'Reason?' }}'); if (! r) return false; this.reason.value = r;" @endif>
    @csrf
    <input type="hidden" name="action" value="{{ $value }}">
    <input type="hidden" name="reason">
    <button class="btn btn-sm {{ $class ?? 'btn-outline-secondary' }}">{{ $label }}</button>
</form>
