<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and OAuth clients belong to an application, and users get several roles.
 *
 * Everything that exists before this migration goes to "api-auth". Each step adds
 * a nullable column, fills it and then makes it required, because SQL Server
 * cannot alter a column that a constraint depends on.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('oauth_scopes', function (Blueprint $table) {
            $table->dropForeign(['api_id']);
        });
        Schema::table('oauth_scopes', function (Blueprint $table) {
            $table->renameColumn('api_id', 'application_id');
        });
        Schema::rename('apis', 'applications');
        Schema::table('oauth_scopes', function (Blueprint $table) {
            $table->foreign('application_id')->references('id')->on('applications');
        });

        $defaultId = $this->defaultApplicationId();

        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('application_id')->nullable();
        });
        DB::table('roles')->update(['application_id' => $defaultId]);
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('application_id')->nullable(false)->change();
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->foreign('application_id')->references('id')->on('applications');
            $table->unique(['application_id', 'name']);
        });

        Schema::connection(config('passport.connection'))->table('oauth_clients', function (Blueprint $table) {
            $table->unsignedBigInteger('application_id')->nullable();
        });
        DB::connection(config('passport.connection'))->table('oauth_clients')->update(['application_id' => $defaultId]);
        Schema::connection(config('passport.connection'))->table('oauth_clients', function (Blueprint $table) {
            $table->unsignedBigInteger('application_id')->nullable(false)->change();
        });
        Schema::connection(config('passport.connection'))->table('oauth_clients', function (Blueprint $table) {
            $table->foreign('application_id')->references('id')->on('applications');
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
        DB::table('role_user')->insertUsing(
            ['user_id', 'role_id'],
            DB::table('users')->whereNotNull('role_id')->select('id', 'role_id'),
        );
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }

    /**
     * Reverse the migrations. A user with several roles keeps only the first one.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained()->nullOnDelete();
        });
        DB::statement('UPDATE users SET role_id = (SELECT TOP 1 role_id FROM role_user WHERE role_user.user_id = users.id ORDER BY role_id)');
        Schema::drop('role_user');

        Schema::connection(config('passport.connection'))->table('oauth_clients', function (Blueprint $table) {
            $table->dropForeign(['application_id']);
        });
        Schema::connection(config('passport.connection'))->table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn('application_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['application_id']);
            $table->dropUnique(['application_id', 'name']);
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('application_id');
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('oauth_scopes', function (Blueprint $table) {
            $table->dropForeign(['application_id']);
        });
        Schema::table('oauth_scopes', function (Blueprint $table) {
            $table->renameColumn('application_id', 'api_id');
        });
        Schema::rename('applications', 'apis');
        Schema::table('oauth_scopes', function (Blueprint $table) {
            $table->foreign('api_id')->references('id')->on('apis');
        });
    }

    /**
     * The application that receives the roles and clients that already exist,
     * created when needed (a fresh database has neither).
     */
    protected function defaultApplicationId(): ?int
    {
        $existing = DB::table('roles')->exists()
            || DB::connection(config('passport.connection'))->table('oauth_clients')->exists();

        if (! $existing) {
            return null;
        }

        return DB::table('applications')->where('name', 'api-auth')->value('id')
            ?? DB::table('applications')->insertGetId([
                'name' => 'api-auth',
                'description' => 'Authentication service (this application)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
};
