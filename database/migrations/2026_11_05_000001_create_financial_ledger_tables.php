<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->string('name', 100);
            // Stable key for accounts the system posts to (cash, member_credit, food_expense, ...).
            $table->string('system_code', 40)->nullable();
            $table->string('account_type', 20);
            $table->char('currency', 3);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->unique(['mess_id', 'system_code']);
            $table->unique(['id', 'mess_id']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->string('transaction_number', 30);
            $table->date('transaction_date');
            $table->string('description');
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('status', 20)->default('posted');
            // A reversal points at the transaction it cancels.
            $table->foreignId('reverses_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('posted_at')->nullable();
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->unique(['mess_id', 'transaction_number']);
            $table->unique('reverses_transaction_id');
            $table->unique(['id', 'mess_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['mess_id', 'transaction_date']);
        });

        Schema::create('ledger_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->foreign(['transaction_id', 'mess_id'])->references(['id', 'mess_id'])->on('ledger_transactions')->restrictOnDelete();
            $table->foreign(['account_id', 'mess_id'])->references(['id', 'mess_id'])->on('financial_accounts')->restrictOnDelete();
            $table->foreign(['member_id', 'mess_id'])->references(['id', 'mess_id'])->on('mess_members')->restrictOnDelete();
            $table->index(['account_id', 'member_id']);
            $table->index('member_id');
        });

        DB::statement('ALTER TABLE ledger_lines ADD CONSTRAINT ledger_lines_amount_check CHECK (debit >= 0 AND credit >= 0 AND ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0)))');

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('idempotency_key', 100);
            $table->char('request_hash', 64);
            $table->json('response_data')->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('ledger_lines');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('financial_accounts');
    }
};
