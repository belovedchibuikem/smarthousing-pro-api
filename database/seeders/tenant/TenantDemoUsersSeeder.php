<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantDemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (!class_exists(\App\Models\Tenant\User::class)) {
            return;
        }

        $admin = \App\Models\Tenant\User::updateOrCreate(
            ['email' => 'admin@tenant.test'],
            [
                'first_name' => 'Tenant',
                'last_name' => 'Admin',
                'password' => Hash::make('Password123!'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // Assign role via Spatie if available
        try {
            if (method_exists($admin, 'assignRole')) {
                $admin->assignRole('tenant-admin');
            }
        } catch (\Throwable $e) {
            // ignore if roles table doesn't exist
        }

        $member = \App\Models\Tenant\User::updateOrCreate(
            ['email' => 'member@tenant.test'],
            [
                'first_name' => 'Demo',
                'last_name' => 'Member',
                'password' => Hash::make('Password123!'),
                'role' => 'member',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        try {
            if (method_exists($member, 'assignRole')) {
                $member->assignRole('member');
            }
        } catch (\Throwable $e) {
            // ignore if roles table doesn't exist
        }

        // Create related member profile if model exists
        try {
            if (class_exists(\App\Models\Tenant\Member::class)) {
                \App\Models\Tenant\Member::firstOrCreate(
                    ['user_id' => $member->id],
                    [
                        'member_number' => 'M-' . substr($member->id, 0, 8),
                        'kyc_status' => 'approved',
                    ]
                );
            }
        } catch (\Throwable $e) {
            // ignore if members table doesn't exist
        }
    }
}


