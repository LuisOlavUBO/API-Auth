<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the application or the role was disabled (reversible). Written by API-IAM, whose
     * migration "aplicaciones-baja" adds them in the shared database.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dateTime('disabled_at')->nullable();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dateTime('disabled_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('disabled_at');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('disabled_at');
        });
    }
};
