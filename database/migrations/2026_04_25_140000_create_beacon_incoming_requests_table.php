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
            $table->json('queries');
            $table->json('models');
            $table->json('jobs');
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beacon_incoming_requests');
    }
};
