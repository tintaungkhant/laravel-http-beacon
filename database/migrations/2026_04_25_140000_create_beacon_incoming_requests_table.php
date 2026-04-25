<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beacon_incoming_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_uuid', 36)->nullable()->index();
            $table->string('hostname')->index();
            $table->string('method', 10)->index();
            $table->string('controller_action', 500)->nullable();
            $table->json('middlewares');
            $table->text('path');
            $table->unsignedSmallInteger('status')->index();
            $table->unsignedInteger('duration_ms')->nullable()->index();
            $table->decimal('memory_mb', 8, 2)->nullable();
            $table->string('ip', 45)->nullable()->index();
            $table->json('payload');
            $table->json('request_headers');
            $table->json('response')->nullable();
            $table->json('response_headers');
            $table->unsignedInteger('query_count')->default(0);
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('beacon_request_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('beacon_incoming_requests')->cascadeOnDelete();
            $table->string('model_class');
            $table->string('model_id')->nullable();
            $table->string('action');
            $table->json('changes')->nullable();
            $table->string('caller', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['model_class', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('beacon_request_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('beacon_incoming_requests')->cascadeOnDelete();
            $table->string('job_class');
            $table->string('connection')->nullable();
            $table->string('queue')->nullable();
            $table->json('payload')->nullable();
            $table->string('caller', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['job_class', 'created_at']);
        });

        Schema::create('beacon_request_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('beacon_incoming_requests')->cascadeOnDelete();
            $table->string('connection')->nullable();
            $table->string('type', 10);
            $table->text('sql');
            $table->json('bindings')->nullable();
            $table->text('sql_with_bindings');
            $table->decimal('time_ms', 10, 2)->index();
            $table->string('caller', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
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
