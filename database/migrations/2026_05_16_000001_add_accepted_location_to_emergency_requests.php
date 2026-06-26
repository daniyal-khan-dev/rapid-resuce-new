<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            $table->decimal('accepted_lat', 10, 7)->nullable()->after('driver_id');
            $table->decimal('accepted_lng', 10, 7)->nullable()->after('accepted_lat');
        });
    }

    public function down(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            $table->dropColumn(['accepted_lat', 'accepted_lng']);
        });
    }
};
