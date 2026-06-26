<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('emergency_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('emergency_request_id');
            $table->string('rreb_id', 20)->nullable();
            $table->string('mobile_no', 20)->nullable();
            $table->string('pickup_address')->nullable();
            $table->string('type', 5)->default('1');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->foreign('emergency_request_id')
                  ->references('id')->on('emergency_requests')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_notifications');
    }
};
