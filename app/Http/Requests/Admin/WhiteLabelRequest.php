<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WhiteLabelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'brand_name' => ['sometimes', 'string', 'max:255'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'company_tagline' => ['nullable', 'string', 'max:500'],
            'company_description' => ['nullable', 'string'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'logo_dark_url' => ['nullable', 'string', 'max:500'],
            'favicon_url' => ['nullable', 'string', 'max:500'],
            'login_background_url' => ['nullable', 'string', 'max:500'],
            'dashboard_hero_url' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_family' => ['sometimes', 'string', 'max:100'],
            'heading_font' => ['nullable', 'string', 'max:100'],
            'body_font' => ['nullable', 'string', 'max:100'],
            'custom_css' => ['nullable', 'string'],
            'custom_js' => ['nullable', 'string'],
            'email_sender_name' => ['nullable', 'string', 'max:255'],
            'email_reply_to' => ['nullable', 'email', 'max:255'],
            'email_footer_text' => ['nullable', 'string'],
            'email_logo_url' => ['nullable', 'string', 'max:500'],
            'terms_url' => ['nullable', 'url', 'max:500'],
            'privacy_url' => ['nullable', 'url', 'max:500'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'help_center_url' => ['nullable', 'url', 'max:500'],
            'footer_text' => ['nullable', 'string'],
            'footer_links' => ['nullable', 'array'],
            'footer_links.*.label' => ['required_with:footer_links', 'string', 'max:255'],
            'footer_links.*.url' => ['required_with:footer_links', 'url', 'max:500'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'url', 'max:500'],
            'social_links.twitter' => ['nullable', 'url', 'max:500'],
            'social_links.linkedin' => ['nullable', 'url', 'max:500'],
            'social_links.instagram' => ['nullable', 'url', 'max:500'],
            'enabled_modules' => ['nullable', 'array'],
            'enabled_modules.*' => ['string', 'in:properties,loans,investments,contributions,wallet,mail_service,statutory_charges,blockchain'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}