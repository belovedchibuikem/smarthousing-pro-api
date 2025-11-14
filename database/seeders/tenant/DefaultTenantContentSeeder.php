<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

class DefaultTenantContentSeeder extends Seeder
{
    public function run(): void
    {
        // Seed sample properties if models exist
        if (class_exists(\App\Models\Tenant\Property::class)) {
            try {
                if (method_exists(\App\Models\Tenant\Property::class, 'factory')) {
                    \App\Models\Tenant\Property::factory()->count(3)->create();
                }
            } catch (\Throwable) {
                // skip if no factory
            }
        }

        // Seed sample loan products/plans if applicable
        if (class_exists(\App\Models\Tenant\LoanProduct::class)) {
            try {
                if (method_exists(\App\Models\Tenant\LoanProduct::class, 'factory')) {
                    \App\Models\Tenant\LoanProduct::factory()->count(3)->create();
                }
            } catch (\Throwable) {
                // skip if no factory
            }
        }

        // Seed sample investment plans if applicable
        if (class_exists(\App\Models\Tenant\InvestmentPlan::class)) {
            try {
                if (method_exists(\App\Models\Tenant\InvestmentPlan::class, 'factory')) {
                    \App\Models\Tenant\InvestmentPlan::factory()->count(3)->create();
                }
            } catch (\Throwable) {
                // skip if no factory
            }
        }
    }
}


