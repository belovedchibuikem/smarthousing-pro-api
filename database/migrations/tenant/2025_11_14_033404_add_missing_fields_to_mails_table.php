<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mails', function (Blueprint $table) {
            $table->string('folder')->nullable()->after('status');
            $table->string('category')->nullable()->after('folder');
            $table->boolean('is_starred')->default(false)->after('category');
            $table->boolean('is_archived')->default(false)->after('is_starred');
            $table->boolean('is_read')->default(false)->after('is_archived');
            $table->boolean('is_urgent')->default(false)->after('is_read');
            $table->string('recipient_type')->nullable()->after('is_urgent'); // 'user', 'department', 'all'
            $table->json('cc')->nullable()->after('recipient_type');
            $table->json('bcc')->nullable()->after('cc');
            $table->timestamp('delivered_at')->nullable()->after('read_at');
            $table->timestamp('failed_at')->nullable()->after('delivered_at');
            $table->text('failure_reason')->nullable()->after('failed_at');
            $table->timestamp('sent_at')->nullable()->change();
            
            $table->index('folder');
            $table->index('category');
            $table->index('is_starred');
            $table->index('is_read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mails', function (Blueprint $table) {
            $table->dropColumn([
                'folder', 'category', 'is_starred', 'is_archived', 
                'is_read', 'is_urgent', 'recipient_type', 'cc', 'bcc',
                'delivered_at', 'failed_at', 'failure_reason'
            ]);
            $table->dropIndex(['folder', 'category', 'is_starred', 'is_read']);
        });
    }
};
