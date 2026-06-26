<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('module', 30);
            $table->string('action', 20);
            $table->string('subject', 120);
            $table->string('preview', 120)->nullable();
            $table->string('module_url', 120)->nullable();
            $table->string('actor', 80)->nullable();
            $table->boolean('is_read')->default(false);
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_notifications');
    }
};
