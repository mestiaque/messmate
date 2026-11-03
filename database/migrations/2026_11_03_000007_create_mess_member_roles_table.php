<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mess_member_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id');
            $table->unsignedBigInteger('member_id');
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The member must belong to the same mess as the assignment row.
            $table->foreign(['member_id', 'mess_id'])->references(['id', 'mess_id'])->on('mess_members')->cascadeOnDelete();
            $table->foreign('mess_id')->references('id')->on('messes')->cascadeOnDelete();
            $table->unique(['member_id', 'role_id']);
            $table->index(['mess_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mess_member_roles');
    }
};
