<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beacon_outgoing_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_uuid', 36)->nullable()->index();
            $table->string('hostname')->index();
            $table->string('method', 10)->index();
            $table->text('uri');
            $table->unsignedSmallInteger('status')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('payload');
            $table->json('request_headers');
            $table->json('response')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('error')->nullable();
            $table->boolean('failed')->default(false)->index();
            $table->dateTime('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beacon_outgoing_requests');
    }
};
