<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->string('name', 100);
            $table->string('category_type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->unique(['mess_id', 'name']);
            $table->unique(['id', 'mess_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('category_id');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->string('allocation_method', 20);
            $table->string('expense_source', 20)->default('manual');
            $table->string('status', 20)->default('submitted');
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('paid_by_member_id')->nullable();
            $table->string('payment_method', 20);
            $table->string('receipt_path')->nullable();
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->foreign(['period_id', 'mess_id'])->references(['id', 'mess_id'])->on('accounting_periods')->restrictOnDelete();
            $table->foreign(['category_id', 'mess_id'])->references(['id', 'mess_id'])->on('expense_categories')->restrictOnDelete();
            $table->foreign(['paid_by_member_id', 'mess_id'])->references(['id', 'mess_id'])->on('mess_members')->restrictOnDelete();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->restrictOnDelete();
            $table->unique(['id', 'mess_id']);
            $table->index(['mess_id', 'expense_date']);
            $table->index(['period_id', 'status']);
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_amount_check CHECK (amount > 0)');

        Schema::create('expense_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id');
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('member_id');
            $table->decimal('allocated_amount', 15, 2);
            // For usage-based allocation: the recorded usage quantity behind the amount.
            $table->decimal('allocation_basis', 15, 4)->nullable();
            $table->timestamps();

            $table->foreign(['expense_id', 'mess_id'])->references(['id', 'mess_id'])->on('expenses')->cascadeOnDelete();
            $table->foreign(['member_id', 'mess_id'])->references(['id', 'mess_id'])->on('mess_members')->restrictOnDelete();
            $table->unique(['expense_id', 'member_id']);
        });

        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_attachments');
        Schema::dropIfExists('expense_allocations');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
