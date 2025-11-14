<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->data['name'] ?? $this->name ?? 'Unknown Business',
            'slug' => $this->id,
            'custom_domain' => $this->data['custom_domain'] ?? null,
            'full_domain' => $this->data['full_domain'] ?? null,
            'logo_url' => $this->data['logo_url'] ?? null,
            'primary_color' => $this->data['primary_color'] ?? '#000000',
            'secondary_color' => $this->data['secondary_color'] ?? '#000000',
            'contact_email' => $this->data['contact_email'] ?? 'contact@example.com',
            'contact_phone' => $this->data['contact_phone'] ?? null,
            'address' => $this->data['address'] ?? null,
            'status' => $this->data['status'] ?? 'active',
            'subscription_status' => $this->data['subscription_status'] ?? 'trial',
            'trial_ends_at' => $this->data['trial_ends_at'] ?? null,
            'subscription_ends_at' => $this->data['subscription_ends_at'] ?? null,
            'settings' => $this->data['settings'] ?? [],
            'subscription' => $this->whenLoaded('subscription', function () {
                return [
                    'id' => $this->subscription->id,
                    'package' => $this->subscription->package->name ?? null,
                    'status' => $this->subscription->status,
                    'ends_at' => $this->subscription->ends_at,
                ];
            }),
            // Additional fields for detail view
            'members_count' => $this->getMembersCount(),
            'properties_count' => $this->getPropertiesCount(),
            'loans_count' => $this->getLoansCount(),
            'monthly_revenue' => $this->getMonthlyRevenue(),
            'total_revenue' => $this->getTotalRevenue(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get member count for this tenant
     */
    private function getMembersCount(): int
    {
        try {
            // Get actual member count from tenant database
            $tenantId = $this->id;
            
            // This would query the tenant's database for actual member count
            // For now, return 0 as we don't have the tenant database connection set up
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get properties count for this tenant
     */
    private function getPropertiesCount(): int
    {
        try {
            // Get actual properties count from tenant database
            $tenantId = $this->id;
            
            // This would query the tenant's database for actual properties count
            // For now, return 0 as we don't have the tenant database connection set up
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get loans count for this tenant
     */
    private function getLoansCount(): int
    {
        try {
            // Get actual loans count from tenant database
            $tenantId = $this->id;
            
            // This would query the tenant's database for actual loans count
            // For now, return 0 as we don't have the tenant database connection set up
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get monthly revenue for this tenant
     */
    private function getMonthlyRevenue(): float
    {
        try {
            // Get actual monthly revenue from platform transactions
            $tenantId = $this->id;
            
            $monthlyRevenue = \Illuminate\Support\Facades\DB::table('platform_transactions')
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount');
                
            return (float) $monthlyRevenue;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get total revenue for this tenant
     */
    private function getTotalRevenue(): float
    {
        try {
            // Get actual total revenue from platform transactions
            $tenantId = $this->id;
            
            $totalRevenue = \Illuminate\Support\Facades\DB::table('platform_transactions')
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->sum('amount');
                
            return (float) $totalRevenue;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
