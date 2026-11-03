<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mess_invitations', function (Blueprint $table) {
            // Set once accepted: the membership the invitation produced.
            $table->foreignId('member_id')->nullable()->after('role_id')->constrained('mess_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mess_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
        });
    }
};
