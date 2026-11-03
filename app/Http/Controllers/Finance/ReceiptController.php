<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Mess;
use App\Models\Tenant;
use App\Services\Finance\ReceiptStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptController extends Controller
{
    private const SOURCES = [
        'bazar' => ['bazarEntries', 'manageBazar', 'approveBazar'],
        'expense' => ['expenses', 'manageExpenses', 'approveExpenses'],
        'deposit' => ['deposits', 'manageDeposits', 'approveDeposits'],
    ];

    public function show(Request $request, Tenant $tenant, Mess $mess, string $type, int $id, ReceiptStorage $receipts): StreamedResponse
    {
        [$relation, $manage, $approve] = self::SOURCES[$type] ?? abort(404);
        $user = $request->user();
        abort_unless($user->can($manage, $mess) || $user->can($approve, $mess) || $user->can('viewFinance', $mess), 403);

        $record = $mess->{$relation}()->findOrFail($id);
        abort_unless($receipts->belongsTo($mess, $record->receipt_path) && Storage::disk('local')->exists($record->receipt_path), 404);

        return Storage::disk('local')->response($record->receipt_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
