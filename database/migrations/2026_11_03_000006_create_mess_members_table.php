<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mess_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('mess_id');
            // Restrict (not set-null): MySQL forbids SET NULL on a base column of a stored generated column.
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('member_code', 32);
            $table->string('full_name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->string('room_number', 32)->nullable();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->string('membership_status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Non-NULL only for a live active membership, so the unique index below
            // allows exactly one active membership per user per mess.
            $table->unsignedBigInteger('active_user_key')->nullable()
                ->storedAs("IF(membership_status = 'active' AND deleted_at IS NULL, user_id, NULL)");

            $table->foreign(['mess_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('messes')->restrictOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();

            $table->unique(['mess_id', 'member_code']);
            $table->unique(['mess_id', 'active_user_key']);
            $table->unique(['id', 'mess_id']);
            $table->index(['tenant_id', 'mess_id']);
            $table->index(['mess_id', 'user_id']);
            $table->index(['mess_id', 'membership_status']);
            $table->index(['mess_id', 'joined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mess_members');
    }
};
