@if ($canManage && $record->status->value !== 'voided' && $record->period->status->acceptsEntries())
    <form method="POST" action="{{ route('meals.approvals.decide', ['type' => $type, 'id' => $record->id]) }}" class="d-flex gap-1"
          onsubmit="const r = prompt('Reason for voiding?'); if (! r) return false; this.reason.value = r;">
        @csrf
        <input type="hidden" name="action" value="void">
        <input type="hidden" name="reason">
        <button class="btn btn-sm btn-link text-danger p-0">Void</button>
    </form>
@endif
