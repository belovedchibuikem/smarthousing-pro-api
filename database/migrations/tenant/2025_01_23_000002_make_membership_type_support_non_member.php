<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if the column exists and modify the enum to include 'non-member'
        if (Schema::hasColumn('members', 'membership_type')) {
            // For MySQL/MariaDB, we need to modify the enum
            DB::statement("ALTER TABLE members MODIFY COLUMN membership_type ENUM('regular', 'premium', 'vip', 'non-member') DEFAULT 'regular'");
        }
    }

    public function down(): void
    {
        // Revert back to original enum values
        if (Schema::hasColumn('members', 'membership_type')) {
            // Update any 'non-member' values to 'regular' before reverting
            DB::table('members')
                ->where('membership_type', 'non-member')
                ->update(['membership_type' => 'regular']);
            
            DB::statement("ALTER TABLE members MODIFY COLUMN membership_type ENUM('regular', 'premium', 'vip') DEFAULT 'regular'");
        }
    }
};

