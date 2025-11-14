<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('mails', function (Blueprint $table) {
            // Add new fields if they don't exist
            if (!Schema::connection('tenant')->hasColumn('mails', 'folder')) {
                $table->enum('folder', ['inbox', 'sent', 'drafts', 'trash'])->default('inbox')->after('status');
            }
            if (!Schema::connection('tenant')->hasColumn('mails', 'category')) {
                $table->enum('category', ['general', 'investment', 'contribution', 'loan', 'property'])->default('general')->after('folder');
            }
            if (!Schema::connection('tenant')->hasColumn('mails', 'is_starred')) {
                $table->boolean('is_starred')->default(false)->after('category');
            }
            if (!Schema::connection('tenant')->hasColumn('mails', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('is_starred');
            }
            if (!Schema::connection('tenant')->hasColumn('mails', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('is_archived');
            }
            if (!Schema::connection('tenant')->hasColumn('mails', 'is_urgent')) {
                $table->boolean('is_urgent')->default(false)->after('is_read');
            }
            if (!Schema::connection('tenant')->hasColumn('mails', 'recipient_type')) {
                $table->enum('recipient_type', ['all', 'active', 'specific', 'group'])->default('specific')->after('recipient_id');
            }
            if (!Schema::connection('tenant')->hasColumn('mails', 'cc')) {
                $table->json('cc')->nullable()->after('body');
            }
            if (!Schema::connection('tenant')->hasColumn('mails', 'bcc')) {
                $table->json('bcc')->nullable()->after('cc');
            }
            if (!Schema::connection('tenant')->hasColumn('mails', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('read_at');
            }
            if (!Schema::connection('tenant')->hasColumn('mails', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('delivered_at');
            }
            if (!Schema::connection('tenant')->hasColumn('mails', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('failed_at');
            }
            
            // Add indexes
            $table->index('folder');
            $table->index('category');
            $table->index('is_starred');
            $table->index('is_archived');
            $table->index('is_read');
            $table->index('recipient_type');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('mails', function (Blueprint $table) {
            $table->dropColumn([
                'folder',
                'category',
                'is_starred',
                'is_archived',
                'is_read',
                'is_urgent',
                'recipient_type',
                'cc',
                'bcc',
                'delivered_at',
                'failed_at',
                'failure_reason',
            ]);
        });
    }
};

