<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_status_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emergency_request_id');
            $table->string('rreb_id', 50)->nullable();
            $table->string('status', 5);
            $table->string('status_label', 50);
            $table->string('driver_name', 100)->nullable();
            $table->string('recipient_type', 10); // 'user' or 'admin'
            $table->unsignedBigInteger('recipient_id')->nullable(); // user_id for user notifs
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_status_notifications');
    }
};
