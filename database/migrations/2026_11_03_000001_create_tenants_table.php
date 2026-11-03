<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->char('default_currency', 3)->default('BDT');
            $table->string('default_timezone', 64)->default('Asia/Dhaka');
            $table->dateTime('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
