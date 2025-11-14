<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentGatewayRequest;
use App\Http\Resources\Admin\PaymentGatewayResource;
use App\Models\Tenant\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    public function index(): JsonResponse
    {
        $gateways = PaymentGateway::where('tenant_id', tenant('id'))->get();

        return response()->json([
            'gateways' => PaymentGatewayResource::collection($gateways)
        ]);
    }

    public function store(PaymentGatewayRequest $request): JsonResponse
    {
        $gateway = PaymentGateway::updateOrCreate(
            [
                'tenant_id' => tenant('id'),
                'gateway_type' => $request->gateway_type
            ],
            [
                'is_enabled' => $request->is_enabled,
                'is_test_mode' => $request->is_test_mode,
                'credentials' => $request->credentials,
                'configuration' => $request->configuration ?? [],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment gateway configured successfully',
            'gateway' => new PaymentGatewayResource($gateway)
        ]);
    }

    public function show(string $gateway): JsonResponse
    {
        // Find gateway by ID or gateway_type (name)
        $gatewayModel = null;
        
        // Try to find by UUID first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $gateway)) {
            $gatewayModel = PaymentGateway::where('tenant_id', tenant('id'))
                ->where('id', $gateway)
                ->first();
        }
        
        // If not found by UUID, try by gateway_type (name)
        if (!$gatewayModel) {
            $gatewayModel = PaymentGateway::where('tenant_id', tenant('id'))
                ->where('gateway_type', $gateway)
                ->first();
        }
        
        if (!$gatewayModel) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway not found'
            ], 404);
        }
        
        return response()->json([
            'gateway' => new PaymentGatewayResource($gatewayModel)
        ]);
    }

    public function update(Request $request, string $gateway): JsonResponse
    {
        // Find gateway by ID or gateway_type (name)
        $gatewayModel = null;
        
        // Try to find by UUID first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $gateway)) {
            $gatewayModel = PaymentGateway::where('tenant_id', tenant('id'))
                ->where('id', $gateway)
                ->first();
        }
        
        // If not found by UUID, try by gateway_type (name)
        if (!$gatewayModel) {
            $gatewayModel = PaymentGateway::where('tenant_id', tenant('id'))
                ->where('gateway_type', $gateway)
                ->first();
        }
        
        // If still not found, create it
        if (!$gatewayModel) {
            $gatewayModel = PaymentGateway::create([
                'tenant_id' => tenant('id'),
                'gateway_type' => $gateway,
                'is_enabled' => false,
                'is_test_mode' => true,
                'credentials' => [],
                'configuration' => [],
            ]);
        }
        
        // Map frontend format to backend format
        $updateData = [];
        
        // Map is_active -> is_enabled
        if ($request->has('is_active')) {
            $updateData['is_enabled'] = $request->input('is_active');
        }
        
        // Map settings -> credentials
        if ($request->has('settings')) {
            $updateData['credentials'] = $request->input('settings');
        }
        
        // Handle other fields if provided
        if ($request->has('is_test_mode')) {
            $updateData['is_test_mode'] = $request->input('is_test_mode');
        }
        
        if ($request->has('credentials')) {
            $updateData['credentials'] = $request->input('credentials');
        }
        
        if ($request->has('configuration')) {
            $updateData['configuration'] = $request->input('configuration');
        }
        
        $gatewayModel->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Payment gateway updated successfully',
            'gateway' => new PaymentGatewayResource($gatewayModel)
        ]);
    }

    public function toggle(string $gateway): JsonResponse
    {
        // Find gateway by ID or gateway_type (name)
        $gatewayModel = null;
        
        // Try to find by UUID first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $gateway)) {
            $gatewayModel = PaymentGateway::where('tenant_id', tenant('id'))
                ->where('id', $gateway)
                ->first();
        }
        
        // If not found by UUID, try by gateway_type (name)
        if (!$gatewayModel) {
            $gatewayModel = PaymentGateway::where('tenant_id', tenant('id'))
                ->where('gateway_type', $gateway)
                ->first();
        }
        
        if (!$gatewayModel) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway not found'
            ], 404);
        }
        
        $gatewayModel->update(['is_enabled' => !$gatewayModel->is_enabled]);

        return response()->json([
            'success' => true,
            'message' => $gatewayModel->is_enabled ? 'Gateway enabled' : 'Gateway disabled',
            'gateway' => new PaymentGatewayResource($gatewayModel)
        ]);
    }

    public function test(string $gateway): JsonResponse
    {
        try {
            // Find gateway by ID or gateway_type (name)
            $gatewayModel = null;
            
            // Try to find by UUID first
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $gateway)) {
                $gatewayModel = PaymentGateway::where('tenant_id', tenant('id'))
                    ->where('id', $gateway)
                    ->first();
            }
            
            // If not found by UUID, try by gateway_type (name)
            if (!$gatewayModel) {
                $gatewayModel = PaymentGateway::where('tenant_id', tenant('id'))
                    ->where('gateway_type', $gateway)
                    ->first();
            }
            
            if (!$gatewayModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment gateway not found'
                ], 404);
            }
            
            // Validate credentials exist before testing
            if (empty($gatewayModel->credentials) || !is_array($gatewayModel->credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gateway credentials are not configured. Please save your API keys first.'
                ], 400);
            }
            
            $testResult = match($gatewayModel->gateway_type) {
                'paystack' => $this->testPaystack($gatewayModel),
                'remita' => $this->testRemita($gatewayModel),
                'stripe' => $this->testStripe($gatewayModel),
                'manual' => ['status' => 'ok', 'message' => 'Manual payment gateway does not require connection testing'],
                default => throw new \InvalidArgumentException('Unsupported gateway type: ' . $gatewayModel->gateway_type)
            };

            return response()->json([
                'success' => true,
                'message' => 'Gateway test successful',
                'test_result' => $testResult
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $errorBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            $errorMessage = $errorBody['message'] ?? $e->getMessage();
            
            return response()->json([
                'success' => false,
                'message' => 'Gateway test failed: ' . $errorMessage,
                'status_code' => $statusCode
            ], 400);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gateway test failed: Unable to connect to gateway. Please check your internet connection and API keys.'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gateway test failed: ' . $e->getMessage()
            ], 400);
        }
    }

    public function destroy(string $gateway): JsonResponse
    {
        // Find gateway by ID or gateway_type (name)
        $gatewayModel = null;
        
        // Try to find by UUID first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $gateway)) {
            $gatewayModel = PaymentGateway::where('tenant_id', tenant('id'))
                ->where('id', $gateway)
                ->first();
        }
        
        // If not found by UUID, try by gateway_type (name)
        if (!$gatewayModel) {
            $gatewayModel = PaymentGateway::where('tenant_id', tenant('id'))
                ->where('gateway_type', $gateway)
                ->first();
        }
        
        if (!$gatewayModel) {
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway not found'
            ], 404);
        }
        
        $gatewayModel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment gateway deleted successfully'
        ]);
    }

    private function testPaystack(PaymentGateway $gateway): array
    {
        // Test Paystack connection
        $clientOptions = [
            'headers' => [
                'Authorization' => 'Bearer ' . ($gateway->credentials['secret_key'] ?? ''),
                'Content-Type' => 'application/json'
            ]
        ];
        
        // Disable SSL verification in local development to avoid certificate issues
        if (app()->environment(['local', 'development'])) {
            $clientOptions['verify'] = false;
        }
        
        $client = new \GuzzleHttp\Client();
        $response = $client->get('https://api.paystack.co/balance', $clientOptions);

        return json_decode($response->getBody(), true);
    }

    private function testRemita(PaymentGateway $gateway): array
    {
        // Test Remita connection
        $clientOptions = [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'merchantId' => $gateway->credentials['merchant_id'] ?? '',
                'serviceTypeId' => $gateway->credentials['service_type_id'] ?? '',
            ]
        ];
        
        // Disable SSL verification in local development to avoid certificate issues
        if (app()->environment(['local', 'development'])) {
            $clientOptions['verify'] = false;
        }
        
        $client = new \GuzzleHttp\Client();
        $response = $client->get('https://remitademo.net/remita/ecomm/init.reg', $clientOptions);

        return json_decode($response->getBody(), true);
    }

    private function testStripe(PaymentGateway $gateway): array
    {
        // Test Stripe connection
        $clientOptions = [
            'headers' => [
                'Authorization' => 'Bearer ' . ($gateway->credentials['secret_key'] ?? ''),
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ];
        
        // Disable SSL verification in local development to avoid certificate issues
        if (app()->environment(['local', 'development'])) {
            $clientOptions['verify'] = false;
        }
        
        $client = new \GuzzleHttp\Client();
        $response = $client->get('https://api.stripe.com/v1/balance', $clientOptions);

        return json_decode($response->getBody(), true);
    }
}
