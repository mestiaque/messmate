<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->string('name', 100);
            $table->string('period_type', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->unique(['id', 'mess_id']);
            $table->index(['mess_id', 'start_date', 'end_date']);
            $table->index(['mess_id', 'status']);
        });

        // Overlap between periods cannot be expressed as a MySQL constraint; it is
        // enforced under a row lock in AccountingPeriodService.
        DB::statement('ALTER TABLE accounting_periods ADD CONSTRAINT accounting_periods_date_range_check CHECK (end_date >= start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
