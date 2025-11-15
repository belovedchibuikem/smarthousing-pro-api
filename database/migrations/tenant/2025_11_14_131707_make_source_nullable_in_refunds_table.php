<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For MySQL/MariaDB, we need to use DB::statement to modify enum columns
        DB::statement('ALTER TABLE `refunds` MODIFY COLUMN `source` ENUM(\'wallet\', \'contribution\', \'investment_return\', \'equity_wallet\') NULL');
        DB::statement('ALTER TABLE `refunds` MODIFY COLUMN `amount` DECIMAL(15, 2) NULL');
    }

    public function down(): void
    {
        // Revert to not nullable (but this might fail if there are null values)
        DB::statement('ALTER TABLE `refunds` MODIFY COLUMN `source` ENUM(\'wallet\', \'contribution\', \'investment_return\', \'equity_wallet\') NOT NULL');
        DB::statement('ALTER TABLE `refunds` MODIFY COLUMN `amount` DECIMAL(15, 2) NOT NULL');
    }
};
