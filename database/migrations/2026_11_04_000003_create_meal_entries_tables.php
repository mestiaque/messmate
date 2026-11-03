<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('member_id');
            $table->date('meal_date');
            $table->string('tracking_mode', 20);
            $table->decimal('total_units', 12, 4)->default(0);
            // Daily-total entries made from a raw count record how the total was derived.
            $table->decimal('raw_count', 12, 4)->nullable();
            $table->decimal('conversion_factor', 12, 6)->nullable();
            $table->string('status', 20)->default('submitted');
            $table->text('notes')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->foreign(['period_id', 'mess_id'])->references(['id', 'mess_id'])->on('accounting_periods')->restrictOnDelete();
            $table->foreign(['member_id', 'mess_id'])->references(['id', 'mess_id'])->on('mess_members')->restrictOnDelete();
            $table->unique(['period_id', 'member_id', 'meal_date']);
            $table->index(['mess_id', 'meal_date']);
            $table->index(['mess_id', 'status']);
            $table->index(['member_id', 'meal_date']);
        });

        DB::statement('ALTER TABLE meal_entries ADD CONSTRAINT meal_entries_units_check CHECK (total_units >= 0)');

        Schema::create('meal_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_type_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_factor_snapshot', 12, 6);
            $table->decimal('weighted_units', 12, 4);
            $table->timestamps();

            $table->unique(['meal_entry_id', 'meal_type_id']);
        });

        DB::statement('ALTER TABLE meal_entry_items ADD CONSTRAINT meal_entry_items_qty_check CHECK (quantity >= 0)');

        Schema::create('meal_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('member_id');
            $table->foreignId('meal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('adjustment_units', 12, 4);
            $table->string('reason');
            $table->string('status', 20)->default('submitted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->foreign(['period_id', 'mess_id'])->references(['id', 'mess_id'])->on('accounting_periods')->restrictOnDelete();
            $table->foreign(['member_id', 'mess_id'])->references(['id', 'mess_id'])->on('mess_members')->restrictOnDelete();
            $table->index(['period_id', 'status']);
        });

        Schema::create('guest_meals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('period_id');
            $table->string('guest_name');
            $table->unsignedBigInteger('sponsor_member_id')->nullable();
            $table->unsignedBigInteger('meal_type_id')->nullable();
            $table->date('meal_date');
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_factor_snapshot', 12, 6);
            $table->decimal('weighted_units', 12, 4);
            $table->decimal('charge_amount', 15, 2)->nullable();
            $table->string('payment_status', 20)->default('not_applicable');
            $table->string('status', 20)->default('submitted');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->foreign(['period_id', 'mess_id'])->references(['id', 'mess_id'])->on('accounting_periods')->restrictOnDelete();
            $table->foreign(['sponsor_member_id', 'mess_id'])->references(['id', 'mess_id'])->on('mess_members')->restrictOnDelete();
            $table->foreign(['meal_type_id', 'mess_id'])->references(['id', 'mess_id'])->on('meal_types')->restrictOnDelete();
            $table->index(['mess_id', 'meal_date']);
            $table->index(['period_id', 'status']);
        });

        DB::statement('ALTER TABLE guest_meals ADD CONSTRAINT guest_meals_amounts_check CHECK (quantity > 0 AND (charge_amount IS NULL OR charge_amount >= 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_meals');
        Schema::dropIfExists('meal_adjustments');
        Schema::dropIfExists('meal_entry_items');
        Schema::dropIfExists('meal_entries');
    }
};
