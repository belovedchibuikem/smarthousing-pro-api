<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LandingPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'template_id' => $this->template_id ?? 'default',
            'is_published' => $this->is_published,
            'sections' => $this->sections,
            'theme' => $this->theme,
            'seo' => $this->seo,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
