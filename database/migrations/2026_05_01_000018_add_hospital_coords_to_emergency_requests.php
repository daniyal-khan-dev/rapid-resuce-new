<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            $table->decimal('hospital_lat', 10, 7)->nullable()->after('hospital_name');
            $table->decimal('hospital_lng', 10, 7)->nullable()->after('hospital_lat');
        });
    }

    public function down(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            $table->dropColumn(['hospital_lat', 'hospital_lng']);
        });
    }
};
