<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The history of logins, failed attempts, blocks and rejected clients (change
     * eventos-de-autenticacion, D1). No foreign keys: an event outlives its user or client.
     */
    public function up(): void
    {
        Schema::create('authentication_events', function (Blueprint $table) {
            $table->id();
            $table->dateTime('occurred_at', 3);
            $table->string('type', 30);
            $table->string('method', 30);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email')->nullable();
            $table->string('client_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('reason', 30)->nullable();

            $table->index('occurred_at');
            $table->index(['user_id', 'occurred_at']);
            $table->index(['ip_address', 'occurred_at']);
            $table->index(['client_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authentication_events');
    }
};
