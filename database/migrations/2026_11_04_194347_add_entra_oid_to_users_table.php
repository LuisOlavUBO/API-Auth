<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The object id (oid) of the user's Microsoft Entra ID account, unique among the users
     * that have one (filtered index, as for "rut"). Users created from Entra ID have no password.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('entra_oid')->nullable()->after('rut');
            $table->string('password')->nullable()->change();
        });

        DB::statement('CREATE UNIQUE INDEX users_entra_oid_unique ON users (entra_oid) WHERE entra_oid IS NOT NULL');
    }

    /**
     * Reverse the migrations. "password" is required again only when every user has one.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_entra_oid_unique');
            $table->dropColumn('entra_oid');
            $table->string('password')->nullable(false)->change();
        });
    }
};
