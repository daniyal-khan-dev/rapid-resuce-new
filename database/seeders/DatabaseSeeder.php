<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Intentionally left empty. Run `php artisan db:seed` does nothing now;
     * add your own seeders here when needed.
     */
    public function run(): void
    {
        // ── ADMIN ─────────────────────────────────────────────────────────────
        $adminId = DB::table('admins')->insertGetId([
            'name'       => 'Super Admin',
            'username'   => 'admin',
            'email'      => 'admin@rapidrescue.com',
            'password'   => Hash::make('Admin@1234'),
            'status'     => '1',
            'added_by'   => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── DRIVER ────────────────────────────────────────────────────────────
        DB::table('drivers')->insertGetId([
            'username'   => 'driver1',
            'name'       => 'Ali Hassan',
            'email'      => 'driver@rapidrescue.com',
            'phone'      => '03001234567',
            'password'   => Hash::make('Driver@1234'),
            'license_no' => 'LIC-001',
            'photo'      => 'default.jpg',
            'status'     => '2',
            'lat'        => 24.891185,
            'lng'        => 67.152557,
            'added_by'   => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── USER ──────────────────────────────────────────────────────────────
        $userId = DB::table('users')->insertGetId([
            'username'    => 'user1',
            'password'    => Hash::make('User@1234'),
            'status'      => '1',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('user_details')->insert([
            'user_id'     => $userId,
            'first_name'  => 'Sara',
            'last_name'   => 'Khan',
            'consumer_no' => 'RREB-USR-001',
            'email'       => 'user@rapidrescue.com',
            'phone'       => '03009876543',
            'address'     => 'PIA Township, Drigh Colony, Karachi',
            'date_of_birth' => '1995-06-15',
        ]);

        // ── 5 EMERGENCY REQUESTS ─────────────────────────────────────────────
        $types = ['1', '1', '2', '1', '2'];
        $hospitals = [
            'CAA Medical Centre',
            'CAA Medical Centre',
            'CAA Medical Centre',
            'CAA Medical Centre',
            'CAA Medical Centre',
        ];

        foreach ($types as $i => $type) {
            $seq = $i + 1;
            DB::table('emergency_requests')->insert([
                'rreb_id'         => 'rreb-' . str_pad($seq, 6, '0', STR_PAD_LEFT),
                'user_id'         => $userId,
                'hospital_name'   => $hospitals[$i],
                'mobile_no'       => '03009876543',
                'email'           => 'user@rapidrescue.com',
                'pickup_address'  => 'PIA Township, Drigh Colony, Gulshan-e-Iqbal Town, Gulshan District, Karachi Division, Sindh, 75230, Pakistan',
                'pickup_lat'      => 24.891185,
                'pickup_lng'      => 67.152557,
                'hospital_lat'    => 24.889792,
                'hospital_lng'    => 67.157348,
                'type'            => $type,
                'status'          => '1',
                'notes'           => 'Demo emergency request #' . $seq,
                'created_at'      => now()->subMinutes(($seq - 1) * 3),
                'updated_at'      => now()->subMinutes(($seq - 1) * 3),
            ]);
        }
    }
}