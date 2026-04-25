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
        Schema::create('beacon_incoming_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_uuid', 36)->nullable();
            $table->string('hostname');
            $table->string('method', 10);
            $table->string('controller_action', 500)->nullable();
            $table->json('middlewares');
            $table->text('path');
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->decimal('memory_mb', 8, 2)->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('payload');
            $table->json('request_headers');
            $table->json('response')->nullable();
            $table->json('response_headers');
            $table->unsignedInteger('query_count')->default(0);
            $table->dateTime('created_at');

            $table->index(['created_at', 'status']);
            $table->index(['created_at', 'duration_ms']);
        });

        Schema::create('beacon_request_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('beacon_incoming_requests')->cascadeOnDelete();
            $table->string('model_class');
            $table->string('model_id')->nullable();
            $table->string('action');
            $table->json('changes')->nullable();
            $table->string('caller', 500)->nullable();
            $table->dateTime('created_at');
        });

        Schema::create('beacon_request_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('beacon_incoming_requests')->cascadeOnDelete();
            $table->string('job_class');
            $table->string('connection')->nullable();
            $table->string('queue')->nullable();
            $table->json('payload')->nullable();
            $table->string('caller', 500)->nullable();
            $table->dateTime('created_at');
        });

        Schema::create('beacon_request_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('beacon_incoming_requests')->cascadeOnDelete();
            $table->string('connection')->nullable();
            $table->string('type', 10);
            $table->text('sql');
            $table->json('bindings')->nullable();
            $table->text('sql_with_bindings');
            $table->decimal('time_ms', 10, 2);
            $table->string('caller', 500)->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beacon_request_queries');
        Schema::dropIfExists('beacon_request_jobs');
        Schema::dropIfExists('beacon_request_models');
        Schema::dropIfExists('beacon_incoming_requests');
    }
};
