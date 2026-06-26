<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_chat_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emergency_request_id');
            $table->unsignedBigInteger('ride_chat_message_id')->nullable();
            $table->string('rreb_id', 30)->nullable();
            $table->enum('recipient_type', ['admin', 'driver', 'user']);
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('sender_name', 120);
            $table->enum('sender_type', ['user', 'driver', 'admin']);
            $table->string('message_preview', 140);
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->foreign('emergency_request_id')
                  ->references('id')->on('emergency_requests')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_chat_notifications');
    }
};
