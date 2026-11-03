<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bazar_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->string('name', 100);
            $table->boolean('is_food_related')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->unique(['mess_id', 'name']);
            $table->unique(['id', 'mess_id']);
        });

        Schema::create('bazar_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('purchased_by');
            $table->date('purchase_date');
            $table->string('entry_mode', 20);
            // Total-mode entries are classified by one category; itemized entries per item.
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('description')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            // Server-computed food-related share of total_amount; the Phase 4 food pool reads this.
            $table->decimal('food_amount', 15, 2);
            $table->string('payment_method', 20);
            $table->string('payment_status', 20);
            $table->string('status', 20)->default('submitted');
            $table->string('rejection_reason')->nullable();
            $table->string('receipt_path')->nullable();
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->foreign(['period_id', 'mess_id'])->references(['id', 'mess_id'])->on('accounting_periods')->restrictOnDelete();
            $table->foreign(['purchased_by', 'mess_id'])->references(['id', 'mess_id'])->on('mess_members')->restrictOnDelete();
            $table->foreign(['category_id', 'mess_id'])->references(['id', 'mess_id'])->on('bazar_categories')->restrictOnDelete();
            $table->foreign('ledger_transaction_id')->references('id')->on('ledger_transactions')->restrictOnDelete();
            $table->index(['mess_id', 'purchase_date']);
            $table->index(['period_id', 'status']);
        });

        DB::statement('ALTER TABLE bazar_entries ADD CONSTRAINT bazar_entries_amount_check CHECK (subtotal >= 0 AND discount >= 0 AND total_amount >= 0 AND total_amount = subtotal - discount AND food_amount >= 0 AND food_amount <= total_amount)');

        Schema::create('bazar_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bazar_entry_id')->constrained()->cascadeOnDelete();
            $table->string('item_name', 150);
            $table->unsignedBigInteger('category_id');
            $table->decimal('quantity', 12, 4);
            $table->string('unit', 20);
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('bazar_categories')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE bazar_items ADD CONSTRAINT bazar_items_amount_check CHECK (quantity > 0 AND unit_price >= 0 AND discount >= 0 AND total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bazar_items');
        Schema::dropIfExists('bazar_entries');
        Schema::dropIfExists('bazar_categories');
    }
};
