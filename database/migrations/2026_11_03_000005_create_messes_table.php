<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('contact_email')->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->string('default_period_mode', 20)->default('calendar');
            $table->string('default_meal_tracking_mode', 20)->default('individual');
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            // Target for composite FKs that pin child rows to the mess's tenant.
            $table->unique(['id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messes');
    }
};
