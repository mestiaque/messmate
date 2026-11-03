<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->string('name', 100);
            $table->string('code', 30);
            $table->decimal('unit_factor', 12, 6);
            $table->boolean('default_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->unique(['mess_id', 'code']);
            $table->unique(['id', 'mess_id']);
        });

        DB::statement('ALTER TABLE meal_types ADD CONSTRAINT meal_types_unit_factor_check CHECK (unit_factor >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_types');
    }
};
