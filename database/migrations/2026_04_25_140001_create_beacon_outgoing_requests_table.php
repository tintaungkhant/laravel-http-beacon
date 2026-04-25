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
        Schema::create('beacon_outgoing_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_uuid', 36)->nullable();
            $table->string('hostname');
            $table->string('method', 10);
            $table->text('uri');
            $table->unsignedSmallInteger('status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('payload');
            $table->json('request_headers');
            $table->json('response')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('error')->nullable();
            $table->boolean('failed')->default(false);
            $table->string('caller_action', 500)->nullable();
            $table->dateTime('created_at');

            $table->index(['created_at', 'status']);
            $table->index(['created_at', 'duration_ms']);
            $table->index(['created_at', 'failed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beacon_outgoing_requests');
    }
};
