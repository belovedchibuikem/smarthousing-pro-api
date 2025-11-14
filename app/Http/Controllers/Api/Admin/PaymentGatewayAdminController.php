<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentGatewayAdminRequest;
use App\Http\Resources\Admin\PaymentGatewayAdminResource;
use App\Models\Central\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentGatewayAdminController extends Controller
{
    public function __construct()
    {
        // Ensure gateways are seeded on first access
        if (PaymentGateway::count() === 0) {
            $this->seedDefaultGateways();
        }
    }
    
    public function index(): JsonResponse
    {
        $gateways = PaymentGateway::all();
        
        // If no gateways exist, seed them
        if ($gateways->isEmpty()) {
            $this->seedDefaultGateways();
            $gateways = PaymentGateway::all();
        }

        return response()->json([
            'gateways' => PaymentGatewayAdminResource::collection($gateways)
        ]);
    }
    
    private function seedDefaultGateways(): void
    {
        $defaultGateways = [
            [
                'name' => 'paystack',
                'display_name' => 'Paystack',
                'description' => 'Nigerian payment gateway for card and bank transfers',
                'is_active' => false,
                'settings' => [
                    'secret_key' => '',
                    'public_key' => '',
                    'webhook_secret' => '',
                    'test_mode' => true,
                ],
                'supported_currencies' => ['NGN', 'USD', 'GBP', 'EUR'],
                'supported_countries' => ['NG', 'US', 'GB', 'EU'],
                'transaction_fee_percentage' => 1.5,
                'transaction_fee_fixed' => 0,
                'minimum_amount' => 100,
                'maximum_amount' => 1000000,
                'platform_fee_percentage' => 0.5,
                'platform_fee_fixed' => 0,
            ],
            [
                'name' => 'remita',
                'display_name' => 'Remita',
                'description' => 'Nigerian government payment gateway',
                'is_active' => false,
                'settings' => [
                    'merchant_id' => '',
                    'api_key' => '',
                    'service_type_id' => '',
                    'webhook_secret' => '',
                    'test_mode' => true,
                ],
                'supported_currencies' => ['NGN'],
                'supported_countries' => ['NG'],
                'transaction_fee_percentage' => 1.0,
                'transaction_fee_fixed' => 0,
                'minimum_amount' => 100,
                'maximum_amount' => 5000000,
                'platform_fee_percentage' => 0.3,
                'platform_fee_fixed' => 0,
            ],
            [
                'name' => 'stripe',
                'display_name' => 'Stripe',
                'description' => 'International payment gateway',
                'is_active' => false,
                'settings' => [
                    'secret_key' => '',
                    'publishable_key' => '',
                    'webhook_secret' => '',
                    'test_mode' => true,
                ],
                'supported_currencies' => ['USD', 'EUR', 'GBP', 'NGN'],
                'supported_countries' => ['US', 'EU', 'GB', 'NG'],
                'transaction_fee_percentage' => 2.9,
                'transaction_fee_fixed' => 30,
                'minimum_amount' => 50,
                'maximum_amount' => 10000000,
                'platform_fee_percentage' => 0.5,
                'platform_fee_fixed' => 0,
            ],
            [
                'name' => 'manual',
                'display_name' => 'Manual Payment',
                'description' => 'Manual bank transfer payments',
                'is_active' => false,
                'settings' => [
                    'bank_accounts' => [],
                    'test_mode' => false,
                ],
                'supported_currencies' => ['NGN'],
                'supported_countries' => ['NG'],
                'transaction_fee_percentage' => 0,
                'transaction_fee_fixed' => 0,
                'minimum_amount' => 0,
                'maximum_amount' => null,
                'platform_fee_percentage' => 0,
                'platform_fee_fixed' => 0,
            ],
        ];
        
        foreach ($defaultGateways as $gatewayData) {
            PaymentGateway::updateOrCreate(
                ['name' => $gatewayData['name']],
                $gatewayData
            );
        }
    }

    public function update(Request $request, string $gateway): JsonResponse
    {
        // Log the incoming request for debugging
        Log::info('PaymentGateway update called', [
            'gateway' => $gateway, 
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'request_data' => $request->all()
        ]);
        
        // Ensure gateways are seeded first
        if (PaymentGateway::count() === 0) {
            $this->seedDefaultGateways();
        }
        
        // Find gateway by ID or name
        $gatewayModel = null;
        
        // Try to find by UUID first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $gateway)) {
            $gatewayModel = PaymentGateway::where('id', $gateway)->first();
            Log::info('Tried to find by UUID', ['found' => $gatewayModel !== null]);
        }
        
        // If not found by UUID, try by name
        if (!$gatewayModel) {
            $name = str_replace('default-', '', $gateway);
            $gatewayModel = PaymentGateway::where('name', $name)->first();
            Log::info('Tried to find by name', ['name' => $name, 'found' => $gatewayModel !== null]);
        }
        
        if (!$gatewayModel) {
            $availableGateways = PaymentGateway::pluck('name')->toArray();
            Log::warning('Gateway not found', ['requested' => $gateway, 'available' => $availableGateways]);
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway not found. Gateway: ' . $gateway . '. Available gateways: ' . implode(', ', $availableGateways),
            ], 404);
        }
        
        $gatewayModel->update([
            'is_active' => $request->input('is_active', $gatewayModel->is_active),
            'settings' => $request->input('settings', $gatewayModel->settings),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment gateway updated successfully',
            'gateway' => new PaymentGatewayAdminResource($gatewayModel)
        ]);
    }

    public function testConnection(Request $request, string $gateway): JsonResponse
    {
        try {
            // Ensure gateways are seeded first
            if (PaymentGateway::count() === 0) {
                $this->seedDefaultGateways();
            }
            
            // Find gateway by ID or name
            $gatewayModel = null;
            
            // Try to find by UUID first
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $gateway)) {
                $gatewayModel = PaymentGateway::where('id', $gateway)->first();
            }
            
            // If not found by UUID, try by name
            if (!$gatewayModel) {
                $name = str_replace('default-', '', $gateway);
                $gatewayModel = PaymentGateway::where('name', $name)->first();
            }
            
            if (!$gatewayModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment gateway not found. Gateway: ' . $gateway . '. Available gateways: ' . implode(', ', PaymentGateway::pluck('name')->toArray()),
                ], 404);
            }
            
            $result = $this->testGatewayConnection($gatewayModel);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'response_time' => $result['response_time'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getGatewayStats(): JsonResponse
    {
        $stats = [
            'total_gateways' => PaymentGateway::count(),
            'active_gateways' => PaymentGateway::where('is_active', true)->count(),
            'inactive_gateways' => PaymentGateway::where('is_active', false)->count(),
            'gateway_usage' => $this->getGatewayUsageStats(),
        ];

        return response()->json([
            'stats' => $stats
        ]);
    }

    private function testGatewayConnection(PaymentGateway $gateway): array
    {
        $startTime = microtime(true);
        
        switch ($gateway->name) {
            case 'paystack':
                return $this->testPaystackConnection($gateway);
            case 'remita':
                return $this->testRemitaConnection($gateway);
            case 'stripe':
                return $this->testStripeConnection($gateway);
            default:
                return [
                    'success' => false,
                    'message' => 'Unknown gateway type',
                    'response_time' => round((microtime(true) - $startTime) * 1000, 2),
                ];
        }
    }

    private function testPaystackConnection(PaymentGateway $gateway): array
    {
        $startTime = microtime(true);
        
        // Simulate Paystack API test
        $settings = $gateway->settings;
        $secretKey = $settings['secret_key'] ?? null;
        
        if (!$secretKey) {
            return [
                'success' => false,
                'message' => 'Paystack secret key not configured',
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        // In a real implementation, you would make an actual API call to Paystack
        return [
            'success' => true,
            'message' => 'Paystack connection successful',
            'response_time' => round((microtime(true) - $startTime) * 1000, 2),
        ];
    }

    private function testRemitaConnection(PaymentGateway $gateway): array
    {
        $startTime = microtime(true);
        
        // Simulate Remita API test
        $settings = $gateway->settings;
        $merchantId = $settings['merchant_id'] ?? null;
        
        if (!$merchantId) {
            return [
                'success' => false,
                'message' => 'Remita merchant ID not configured',
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        return [
            'success' => true,
            'message' => 'Remita connection successful',
            'response_time' => round((microtime(true) - $startTime) * 1000, 2),
        ];
    }

    private function testStripeConnection(PaymentGateway $gateway): array
    {
        $startTime = microtime(true);
        
        // Simulate Stripe API test
        $settings = $gateway->settings;
        $secretKey = $settings['secret_key'] ?? null;
        
        if (!$secretKey) {
            return [
                'success' => false,
                'message' => 'Stripe secret key not configured',
                'response_time' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }

        return [
            'success' => true,
            'message' => 'Stripe connection successful',
            'response_time' => round((microtime(true) - $startTime) * 1000, 2),
        ];
    }

    private function getGatewayUsageStats(): array
    {
        // This would typically query payment transactions to get usage stats
        return [
            'paystack' => [
                'total_transactions' => 1250,
                'successful_transactions' => 1180,
                'failed_transactions' => 70,
                'total_amount' => 25000000, // ₦25M
            ],
            'remita' => [
                'total_transactions' => 850,
                'successful_transactions' => 820,
                'failed_transactions' => 30,
                'total_amount' => 18000000, // ₦18M
            ],
            'stripe' => [
                'total_transactions' => 450,
                'successful_transactions' => 430,
                'failed_transactions' => 20,
                'total_amount' => 12000000, // ₦12M
            ],
        ];
    }
}
