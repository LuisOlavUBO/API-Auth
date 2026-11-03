<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional RUT, unique among the users that have one. The index is filtered
     * because a plain unique index in SQL Server allows a single NULL.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rut', 10)->nullable()->after('uuid');
        });

        DB::statement('CREATE UNIQUE INDEX users_rut_unique ON users (rut) WHERE rut IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_rut_unique');
            $table->dropColumn('rut');
        });
    }
};
