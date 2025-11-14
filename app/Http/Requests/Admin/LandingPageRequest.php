<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LandingPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id' => 'nullable|string|in:default,modern,classic',
            'sections' => 'required|array',
            'sections.*.id' => 'required|string',
            'sections.*.type' => 'required|in:hero,features,properties,investments,loans,how-it-works,cta,stats,testimonials',
            'sections.*.visible' => 'required|boolean',
            'sections.*.position' => 'required|integer|min:0',
            'sections.*.config' => 'nullable|array',
            'sections.*.config.data_source' => 'nullable|string|in:all_active,selected',
            'sections.*.config.selected_ids' => 'nullable|array',
            'sections.*.config.selected_ids.*' => 'nullable|uuid',
            'sections.*.config.sort_by' => 'nullable|string',
            'sections.*.config.sort_order' => 'nullable|string|in:asc,desc',
            'theme' => 'required|array',
            'theme.primary_color' => 'required|regex:/^#[0-9A-F]{6}$/i',
            'theme.secondary_color' => 'required|regex:/^#[0-9A-F]{6}$/i',
            'theme.accent_color' => 'nullable|regex:/^#[0-9A-F]{6}$/i',
            'theme.font_family' => 'nullable|string|max:100',
            'seo' => 'required|array',
            'seo.title' => 'required|string|max:60',
            'seo.description' => 'required|string|max:160'
        ];
    }
}
