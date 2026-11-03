<?php

namespace App\Services\Finance;

use App\Models\Mess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Receipts live on the private "local" disk and are only served through an
 * authorized route, never from a public URL.
 */
class ReceiptStorage
{
    public const RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'];

    public function store(Mess $mess, ?UploadedFile $file): ?string
    {
        return $file ? Storage::disk('local')->putFile("receipts/{$mess->tenant_id}/{$mess->id}", $file) : null;
    }

    public function belongsTo(Mess $mess, ?string $path): bool
    {
        return $path !== null && str_starts_with($path, "receipts/{$mess->tenant_id}/{$mess->id}/");
    }
}
