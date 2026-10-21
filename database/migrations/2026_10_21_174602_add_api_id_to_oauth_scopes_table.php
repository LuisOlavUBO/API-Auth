<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owners of the scopes that existed before scopes were grouped by API.
     *
     * @var array<string, array{description: string, scopes: string[]}>
     */
    protected array $owners = [
        'api-auth' => [
            'description' => 'Authentication service (this application)',
            'scopes' => ['user:read', 'user:write', 'introspect'],
        ],
        'api-maestros' => [
            'description' => 'Master data API',
            'scopes' => ['geografia:leer', 'sistemas:leer', 'homologacion:leer'],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('oauth_scopes', function (Blueprint $table) {
            $table->unsignedBigInteger('api_id')->nullable();
        });

        foreach ($this->owners as $name => $owner) {
            if (! DB::table('oauth_scopes')->whereIn('name', $owner['scopes'])->exists()) {
                continue;
            }

            $apiId = DB::table('apis')->insertGetId([
                'name' => $name,
                'description' => $owner['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('oauth_scopes')->whereIn('name', $owner['scopes'])->update(['api_id' => $apiId]);
        }

        $orphans = DB::table('oauth_scopes')->whereNull('api_id')->pluck('name');

        if ($orphans->isNotEmpty()) {
            throw new RuntimeException('Assign an API to these scopes before migrating: '.$orphans->implode(', '));
        }

        // The column becomes required before the foreign key exists, since
        // SQL Server cannot alter a column that a constraint depends on.
        Schema::table('oauth_scopes', function (Blueprint $table) {
            $table->unsignedBigInteger('api_id')->nullable(false)->change();
        });

        Schema::table('oauth_scopes', function (Blueprint $table) {
            $table->foreign('api_id')->references('id')->on('apis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_scopes', function (Blueprint $table) {
            $table->dropForeign(['api_id']);
            $table->dropColumn('api_id');
        });
    }
};
