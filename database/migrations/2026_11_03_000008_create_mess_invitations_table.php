<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mess_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->char('invitation_token_hash', 64)->unique();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->cascadeOnDelete();
            $table->index(['mess_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mess_invitations');
    }
};
