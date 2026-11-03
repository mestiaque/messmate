<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('member_id');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 20);
            $table->unsignedBigInteger('financial_account_id');
            $table->string('transaction_reference', 100)->nullable();
            $table->string('receipt_path')->nullable();
            $table->dateTime('paid_at');
            $table->string('status', 20)->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->foreign(['period_id', 'mess_id'])->references(['id', 'mess_id'])->on('accounting_periods')->restrictOnDelete();
            $table->foreign(['member_id', 'mess_id'])->references(['id', 'mess_id'])->on('mess_members')->restrictOnDelete();
            $table->foreign(['financial_account_id', 'mess_id'])->references(['id', 'mess_id'])->on('financial_accounts')->restrictOnDelete();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->restrictOnDelete();
            // The same bank/wallet reference cannot be recorded twice in a mess.
            $table->unique(['mess_id', 'payment_method', 'transaction_reference']);
            $table->index(['period_id', 'status']);
            $table->index(['member_id', 'status']);
        });

        DB::statement('ALTER TABLE deposits ADD CONSTRAINT deposits_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
