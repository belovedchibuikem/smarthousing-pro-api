<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('slug');
            $table->index('is_active');
        });

        // Seed default modules
        DB::table('modules')->insert([
            [
                'id' => Str::uuid(),
                'name' => 'Member Management',
                'slug' => 'members',
                'description' => 'Manage cooperative members and KYC',
                'icon' => 'Users',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Contributions',
                'slug' => 'contributions',
                'description' => 'Track member contributions and payments',
                'icon' => 'Wallet',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Loans',
                'slug' => 'loans',
                'description' => 'Loan products and applications',
                'icon' => 'TrendingUp',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Properties',
                'slug' => 'properties',
                'description' => 'Property listings and management',
                'icon' => 'Building2',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Investments',
                'slug' => 'investments',
                'description' => 'Investment plans and tracking',
                'icon' => 'PieChart',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Mortgages',
                'slug' => 'mortgages',
                'description' => 'Mortgage management',
                'icon' => 'Home',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Mail Service',
                'slug' => 'mail',
                'description' => 'Internal messaging system',
                'icon' => 'Mail',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Reports',
                'slug' => 'reports',
                'description' => 'Analytics and reporting',
                'icon' => 'BarChart',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Documents',
                'slug' => 'documents',
                'description' => 'Document management',
                'icon' => 'FileText',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Statutory Charges',
                'slug' => 'statutory',
                'description' => 'Statutory charges management',
                'icon' => 'Receipt',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
