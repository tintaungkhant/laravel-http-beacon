<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('beacon.storage.connection');
    }

    public function up(): void
    {
        Schema::create('beacon_shared_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('request_type', 10);          // 'incoming' | 'outgoing'
            $table->unsignedBigInteger('request_id');     // polymorphic across two tables, no FK
            $table->string('password')->nullable();        // hashed; null = no password
            $table->dateTime('expires_at')->nullable();    // null = never expires
            $table->dateTime('revoked_at')->nullable();    // set = revoked
            $table->unsignedInteger('view_count')->default(0);
            $table->dateTime('last_viewed_at')->nullable();
            $table->dateTime('created_at');

            $table->index(['request_type', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beacon_shared_links');
    }
};
