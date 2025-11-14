<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomDomainResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Extract subdomain from domain if it exists
        $parts = explode('.', $this->domain, 2);
        $subdomain = count($parts) > 1 && strpos($parts[0], '.') === false ? $parts[0] : null;
        $domainName = $subdomain ? $parts[1] : $this->domain;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'domain_name' => $domainName,
            'subdomain' => $subdomain,
            'full_domain' => $this->domain,
            'status' => $this->status,
            'status_message' => $this->getStatusMessage(),
            'verification_token' => $this->verification_token,
            'dns_records' => $this->dns_records ?? [],
            'requested_at' => $this->created_at?->toIso8601String() ?? $this->created_at,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'ssl_status' => $this->ssl_enabled ? 'active' : ($this->status === 'active' ? 'pending' : null),
            'admin_notes' => $this->admin_notes,
        ];
    }

    private function getStatusMessage(): string
    {
        return match($this->status) {
            'pending' => 'Awaiting DNS verification',
            'verifying' => 'Verifying DNS records',
            'verified' => 'Domain verified successfully',
            'active' => 'Domain is active',
            'failed' => 'Domain verification failed',
            'rejected' => 'Domain request rejected',
            default => 'Unknown status'
        };
    }
}
