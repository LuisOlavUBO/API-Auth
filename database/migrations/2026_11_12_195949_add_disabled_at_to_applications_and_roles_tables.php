<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['applications', 'roles'];

    /**
     * When the application or the role was disabled (reversible). API-IAM owns these tables and adds the
     * columns in the shared database with its migration "aplicaciones-baja"; this copy exists for the test
     * database, so each column is added only when missing and running it where API-IAM already did is harmless.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'disabled_at')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dateTime('disabled_at')->nullable());
            }
        }
    }

    /**
     * Reverse the migrations. Only meaningful in testing: the columns belong to API-IAM.
     */
    public function down(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'disabled_at')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('disabled_at'));
            }
        }
    }
};
