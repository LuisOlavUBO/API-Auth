<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the user was disabled (no more tokens), and until when its password is a temporary
     * one that must be changed on the next login. Both are written by API-IAM.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('disabled_at')->nullable()->after('entra_oid');
            $table->dateTime('temporary_password_expires_at')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['disabled_at', 'temporary_password_expires_at']);
        });
    }
};
