<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional link to the person in API-Personas (its institutional "id"), unique among
     * the users that have one. API-IAM writes it; API-Auth only reads it. The index is
     * filtered because a plain unique index in SQL Server allows a single NULL.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id_persona')->nullable()->after('uuid');
        });

        DB::statement('CREATE UNIQUE INDEX users_id_persona_unique ON users (id_persona) WHERE id_persona IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_id_persona_unique');
            $table->dropColumn('id_persona');
        });
    }
};
