<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('white_label_settings', function (Blueprint $table) {
            // Company information
            $table->string('company_name')->nullable()->after('brand_name');
            $table->string('company_tagline')->nullable()->after('company_name');
            $table->text('company_description')->nullable()->after('company_tagline');
            
            // Additional logo and images
            $table->string('logo_dark_url')->nullable()->after('logo_url');
            $table->string('login_background_url')->nullable()->after('favicon_url');
            $table->string('dashboard_hero_url')->nullable()->after('login_background_url');
            
            // Colors
            $table->string('background_color', 7)->nullable()->after('accent_color');
            $table->string('text_color', 7)->nullable()->after('background_color');
            
            // Typography
            $table->string('heading_font')->nullable()->after('font_family');
            $table->string('body_font')->nullable()->after('heading_font');
            
            // Email settings
            $table->string('email_sender_name')->nullable();
            $table->string('email_reply_to')->nullable();
            $table->text('email_footer_text')->nullable();
            $table->string('email_logo_url')->nullable();
            
            // Content & Links
            $table->string('terms_url')->nullable();
            $table->string('privacy_url')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('help_center_url')->nullable();
            $table->text('footer_text')->nullable();
            $table->json('footer_links')->nullable();
            $table->json('social_links')->nullable();
            
            // Modules
            $table->json('enabled_modules')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('white_label_settings', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'company_tagline',
                'company_description',
                'logo_dark_url',
                'login_background_url',
                'dashboard_hero_url',
                'background_color',
                'text_color',
                'heading_font',
                'body_font',
                'email_sender_name',
                'email_reply_to',
                'email_footer_text',
                'email_logo_url',
                'terms_url',
                'privacy_url',
                'support_email',
                'support_phone',
                'help_center_url',
                'footer_text',
                'footer_links',
                'social_links',
                'enabled_modules',
            ]);
        });
    }
};
