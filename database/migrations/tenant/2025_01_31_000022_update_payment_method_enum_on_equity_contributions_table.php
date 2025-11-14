<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `equity_contributions` MODIFY `payment_method` ENUM('paystack','remita','stripe','manual','bank_transfer','wallet') DEFAULT 'manual'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `equity_contributions` MODIFY `payment_method` ENUM('paystack','remita','stripe','manual','bank_transfer') DEFAULT 'manual'");
    }
};

