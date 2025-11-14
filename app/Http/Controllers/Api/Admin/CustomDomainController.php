<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomDomainRequest;
use App\Http\Resources\Admin\CustomDomainResource;
use App\Models\Central\CustomDomainRequest as CustomDomainRequestModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomDomainController extends Controller
{
    public function index(): JsonResponse
    {
        $domains = CustomDomainRequestModel::where('tenant_id', tenant('id'))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'domains' => CustomDomainResource::collection($domains)
        ]);
    }

    public function store(CustomDomainRequest $request): JsonResponse
    {
        // Check if domain already exists for this tenant
        $existingDomain = CustomDomainRequestModel::where('tenant_id', tenant('id'))
            ->where('domain', $request->domain_name)
            ->first();

        if ($existingDomain) {
            return response()->json([
                'success' => false,
                'message' => 'This domain has already been requested. Please check your existing domain requests.'
            ], 422);
        }

        // Build full domain
        $fullDomain = $request->subdomain 
            ? $request->subdomain . '.' . $request->domain_name
            : $request->domain_name;

        $verificationToken = 'frsc-verify-' . Str::random(16);

        // Generate DNS records based on configuration
        $targetDomain = config('app.domain', 'frsc-housing.vercel.app');
        
        $dnsRecords = [];
        
        // Add CNAME or A record for the domain/subdomain
        if ($request->subdomain) {
            $dnsRecords[] = [
                'type' => 'CNAME',
                'name' => $request->subdomain,
                'value' => $targetDomain,
                'description' => "Point {$request->subdomain} to {$targetDomain}"
            ];
        } else {
            // For root domain, we might need A record (IP address) or CNAME
            // Using CNAME for now, but might need to be A record for root domains
            $dnsRecords[] = [
                'type' => 'CNAME',
                'name' => 'www',
                'value' => $targetDomain,
                'description' => "Point www to {$targetDomain}"
            ];
        }

        // Add verification TXT record
        $dnsRecords[] = [
            'type' => 'TXT',
            'name' => '_frsc-verify',
            'value' => $verificationToken,
            'description' => 'Domain verification token'
        ];

        $domain = CustomDomainRequestModel::create([
            'tenant_id' => tenant('id'),
            'domain' => $fullDomain,
            'verification_token' => $verificationToken,
            'status' => 'pending',
            'dns_records' => $dnsRecords,
            'ssl_enabled' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom domain request created successfully. Please configure the DNS records below.',
            'domain' => new CustomDomainResource($domain)
        ]);
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $domain = CustomDomainRequestModel::where('id', $id)
            ->where('tenant_id', tenant('id'))
            ->firstOrFail();

        if ($domain->status === 'active') {
            return response()->json([
                'success' => true,
                'message' => 'Domain is already active',
                'domain' => new CustomDomainResource($domain)
            ]);
        }

        // Check DNS records
        $verificationResult = $this->checkDnsRecords($domain);

        if ($verificationResult['verified']) {
            $domain->update([
                'status' => 'verified',
                'verified_at' => now(),
                'admin_notes' => $verificationResult['message'] ?? null
            ]);

            // Auto-activate if verified
            $domain->update([
                'status' => 'active',
                'activated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Domain verified and activated successfully',
                'domain' => new CustomDomainResource($domain->fresh())
            ]);
        }

        $domain->update([
            'status' => 'failed',
            'admin_notes' => $verificationResult['message'] ?? 'DNS verification failed'
        ]);

        return response()->json([
            'success' => false,
            'message' => $verificationResult['message'] ?? 'Domain verification failed. Please check your DNS records.',
            'domain' => new CustomDomainResource($domain->fresh())
        ], 400);
    }

    public function checkVerification(string $id): JsonResponse
    {
        $domain = CustomDomainRequestModel::where('id', $id)
            ->where('tenant_id', tenant('id'))
            ->firstOrFail();

        if ($domain->status === 'active') {
            return response()->json([
                'success' => true,
                'verified' => true,
                'message' => 'Domain is already active',
                'domain' => new CustomDomainResource($domain)
            ]);
        }

        $verificationResult = $this->checkDnsRecords($domain);

        if ($verificationResult['verified']) {
            $domain->update([
                'status' => 'verified',
                'verified_at' => now(),
            ]);

            // Auto-activate if verified
            $domain->update([
                'status' => 'active',
                'activated_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'verified' => $verificationResult['verified'],
            'message' => $verificationResult['message'] ?? ($verificationResult['verified'] ? 'Domain verified successfully' : 'DNS records not found'),
            'domain' => new CustomDomainResource($domain->fresh())
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $domain = CustomDomainRequestModel::where('id', $id)
            ->where('tenant_id', tenant('id'))
            ->firstOrFail();

        // Only allow deletion of non-active domains
        if ($domain->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an active domain. Please contact support to deactivate it first.'
            ], 400);
        }

        $domain->delete();

        return response()->json([
            'success' => true,
            'message' => 'Domain request deleted successfully'
        ]);
    }

    private function checkDnsRecords(CustomDomainRequestModel $domain): array
    {
        $domainName = $domain->domain;
        $dnsRecords = $domain->dns_records ?? [];
        
        if (empty($dnsRecords)) {
            return [
                'verified' => false,
                'message' => 'No DNS records configured'
            ];
        }

        $allVerified = true;
        $messages = [];

        foreach ($dnsRecords as $record) {
            $recordType = $record['type'] ?? '';
            $recordName = $record['name'] ?? '';
            $expectedValue = $record['value'] ?? '';

            if (empty($recordType) || empty($expectedValue)) {
                continue;
            }

            $checkDomain = $recordName === '@' || $recordName === '' 
                ? $domainName 
                : ($recordName . '.' . $domainName);

            try {
                // Map record type to DNS constant
                $dnsConstant = match($recordType) {
                    'TXT' => DNS_TXT,
                    'CNAME' => DNS_CNAME,
                    'A' => DNS_A,
                    'AAAA' => DNS_AAAA,
                    default => null
                };

                if (!$dnsConstant) {
                    $allVerified = false;
                    $messages[] = "Unsupported DNS record type: {$recordType}";
                    continue;
                }

                $dnsRecords = @dns_get_record($checkDomain, $dnsConstant);
                
                if ($dnsRecords === false || empty($dnsRecords)) {
                    $allVerified = false;
                    $messages[] = "{$recordType} record for {$recordName} not found";
                    continue;
                }

                $found = false;
                foreach ($dnsRecords as $dnsRecord) {
                    $recordKey = match($recordType) {
                        'TXT' => 'txt',
                        'CNAME' => 'target',
                        'A' => 'ip',
                        'AAAA' => 'ipv6',
                        default => null
                    };

                    if ($recordKey && isset($dnsRecord[$recordKey])) {
                        $actualValue = is_array($dnsRecord[$recordKey]) 
                            ? implode('', $dnsRecord[$recordKey])
                            : $dnsRecord[$recordKey];

                        // For TXT records, check if expected value is contained
                        if ($recordType === 'TXT') {
                            if (str_contains($actualValue, $expectedValue)) {
                                $found = true;
                                break;
                            }
                        } else {
                            // For other record types, exact match (case-insensitive)
                            // Remove trailing dots that DNS sometimes adds
                            $actualValue = rtrim($actualValue, '.');
                            $expectedValue = rtrim($expectedValue, '.');
                            if (strtolower(trim($actualValue)) === strtolower(trim($expectedValue))) {
                                $found = true;
                                break;
                            }
                        }
                    }
                }

                if (!$found) {
                    $allVerified = false;
                    $messages[] = "{$recordType} record for {$recordName} not found or incorrect";
                }
            } catch (\Exception $e) {
                $allVerified = false;
                $messages[] = "Failed to check {$recordType} record: " . $e->getMessage();
            }
        }

        return [
            'verified' => $allVerified,
            'message' => $allVerified 
                ? 'All DNS records verified successfully' 
                : implode('. ', $messages)
        ];
    }
}
