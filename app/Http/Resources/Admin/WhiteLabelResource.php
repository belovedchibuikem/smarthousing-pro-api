<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhiteLabelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Build full URL for stored files
        $logoUrl = $this->logo_url ? (str_starts_with($this->logo_url, 'http') ? $this->logo_url : asset('storage/' . $this->logo_url)) : null;
        $logoDarkUrl = $this->logo_dark_url ? (str_starts_with($this->logo_dark_url, 'http') ? $this->logo_dark_url : asset('storage/' . $this->logo_dark_url)) : null;
        $faviconUrl = $this->favicon_url ? (str_starts_with($this->favicon_url, 'http') ? $this->favicon_url : asset('storage/' . $this->favicon_url)) : null;
        $loginBgUrl = $this->login_background_url ? (str_starts_with($this->login_background_url, 'http') ? $this->login_background_url : asset('storage/' . $this->login_background_url)) : null;
        $dashboardHeroUrl = $this->dashboard_hero_url ? (str_starts_with($this->dashboard_hero_url, 'http') ? $this->dashboard_hero_url : asset('storage/' . $this->dashboard_hero_url)) : null;
        $emailLogoUrl = $this->email_logo_url ? (str_starts_with($this->email_logo_url, 'http') ? $this->email_logo_url : asset('storage/' . $this->email_logo_url)) : null;
        
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'brand_name' => $this->brand_name ?? $this->company_name,
            'company_name' => $this->company_name ?? $this->brand_name,
            'company_tagline' => $this->company_tagline ?? '',
            'company_description' => $this->company_description ?? '',
            'logo_url' => $logoUrl ?? '',
            'logo_dark_url' => $logoDarkUrl ?? '',
            'favicon_url' => $faviconUrl ?? '',
            'login_background_url' => $loginBgUrl ?? '',
            'dashboard_hero_url' => $dashboardHeroUrl ?? '',
            'primary_color' => $this->primary_color ?? '#3b82f6',
            'secondary_color' => $this->secondary_color ?? '#8b5cf6',
            'accent_color' => $this->accent_color ?? '#10b981',
            'background_color' => $this->background_color ?? '#ffffff',
            'text_color' => $this->text_color ?? '#1f2937',
            'font_family' => $this->font_family ?? 'Inter',
            'heading_font' => $this->heading_font ?? $this->font_family ?? 'Inter',
            'body_font' => $this->body_font ?? $this->font_family ?? 'Inter',
            'custom_css' => $this->custom_css ?? '',
            'custom_js' => $this->custom_js ?? '',
            'email_sender_name' => $this->email_sender_name ?? '',
            'email_reply_to' => $this->email_reply_to ?? '',
            'email_footer_text' => $this->email_footer_text ?? '',
            'email_logo_url' => $emailLogoUrl ?? '',
            'terms_url' => $this->terms_url ?? '',
            'privacy_url' => $this->privacy_url ?? '',
            'support_email' => $this->support_email ?? '',
            'support_phone' => $this->support_phone ?? '',
            'help_center_url' => $this->help_center_url ?? '',
            'footer_text' => $this->footer_text ?? '',
            'footer_links' => $this->footer_links ?? [],
            'social_links' => $this->social_links ?? [],
            'enabled_modules' => $this->enabled_modules ?? ['properties', 'loans', 'investments', 'contributions', 'wallet'],
            'is_active' => $this->is_active ?? false,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}