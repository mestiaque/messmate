@if ($tx)
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold">Ledger {{ $tx->transaction_number }} @if ($tx->status === 'reversed')<span class="badge text-bg-dark">reversed</span>@endif</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Account</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                <tbody>
                @foreach ($tx->lines as $line)
                    <tr><td>{{ $line->account->name }}@if ($line->member) <span class="text-muted small">· {{ $line->member->full_name }}</span>@endif</td>
                        <td class="text-end">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                        <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
