<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mess_id')->nullable()->constrained('messes')->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'mess_id', 'key']);
        });

        Schema::create('financial_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('member_id');
            $table->string('adjustment_type', 10); // credit = mess owes member more, debit = member owes more
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->string('status', 20)->default('submitted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->date('effective_date');
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->foreign(['period_id', 'mess_id'])->references(['id', 'mess_id'])->on('accounting_periods')->restrictOnDelete();
            $table->foreign(['member_id', 'mess_id'])->references(['id', 'mess_id'])->on('mess_members')->restrictOnDelete();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->restrictOnDelete();
            $table->index(['period_id', 'status']);
        });

        DB::statement('ALTER TABLE financial_adjustments ADD CONSTRAINT financial_adjustments_amount_check CHECK (amount > 0)');

        Schema::create('monthly_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('period_id');
            $table->unsignedInteger('calculation_version');
            // draft = regenerable preview; final = immutable; superseded = replaced after a reopen.
            $table->string('status', 20);
            $table->string('strategy', 30);
            $table->decimal('total_food_expense', 15, 2);
            $table->decimal('total_weighted_meals', 15, 4);
            $table->decimal('calculated_meal_rate', 15, 6);
            $table->decimal('total_other_expenses', 15, 2);
            $table->decimal('total_deposits', 15, 2);
            $table->decimal('total_adjustments', 15, 2);
            $table->json('calculation_details');
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('generated_at');
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('superseded_at')->nullable();
            $table->string('superseded_reason')->nullable();
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->foreign(['period_id', 'mess_id'])->references(['id', 'mess_id'])->on('accounting_periods')->restrictOnDelete();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->restrictOnDelete();
            $table->unique(['period_id', 'calculation_version']);
            $table->index(['period_id', 'status']);
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('period_id');
            $table->foreignId('snapshot_id')->constrained('monthly_snapshots')->cascadeOnDelete();
            $table->unsignedBigInteger('member_id');
            $table->decimal('opening_credit', 15, 2);
            $table->decimal('meal_units', 15, 4);
            $table->decimal('meal_cost', 15, 2);
            $table->decimal('guest_cost', 15, 2);
            $table->decimal('other_expense', 15, 2);
            $table->decimal('other_credits', 15, 2);
            $table->decimal('other_debits', 15, 2);
            $table->decimal('total_deposits', 15, 2);
            $table->decimal('total_payable', 15, 2);
            $table->decimal('closing_credit', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status', 20);
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->foreign(['period_id', 'mess_id'])->references(['id', 'mess_id'])->on('accounting_periods')->restrictOnDelete();
            $table->foreign(['member_id', 'mess_id'])->references(['id', 'mess_id'])->on('mess_members')->restrictOnDelete();
            $table->unique(['snapshot_id', 'member_id']);
            $table->index(['member_id', 'status']);
        });

        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 30);
            $table->string('description');
            $table->decimal('quantity', 15, 4)->nullable();
            $table->decimal('unit_rate', 15, 6)->nullable();
            $table->decimal('amount', 15, 2); // signed: + increases what the mess owes the member
            $table->json('calculation_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('settlement_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('mess_id');
            $table->decimal('amount', 15, 2);
            $table->string('payment_type', 20); // collection (member pays mess) or refund (mess pays member)
            $table->string('payment_method', 20);
            $table->string('transaction_reference', 100)->nullable();
            $table->unsignedBigInteger('ledger_transaction_id');
            $table->dateTime('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->restrictOnDelete();
            $table->foreign('mess_id')->references('id')->on('messes')->restrictOnDelete();
            $table->index(['mess_id', 'paid_at']);
        });

        DB::statement('ALTER TABLE settlement_payments ADD CONSTRAINT settlement_payments_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_payments');
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('monthly_snapshots');
        Schema::dropIfExists('financial_adjustments');
        Schema::dropIfExists('system_settings');
    }
};
