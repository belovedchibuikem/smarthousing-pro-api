<?php

namespace App\Http\Requests\Subscriptions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use App\Models\Central\PaymentGateway;
use App\Models\Central\Package;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class InitializeSubscriptionRequest extends FormRequest
{
   

    public function rules(): array
    {
        $rules = [
            'package_id' => ['required', function ($attribute, $value, $fail) {
                $exists = DB::connection('mysql')
                    ->table('packages')
                    ->where('id', $value)
                    ->where('is_active', true)
                    ->exists();
                
                if (!$exists) {
                    $fail('The selected package is invalid or inactive.');
                }
            }],
            'payment_method' => 'required|in:paystack,remita,stripe,wallet,manual',
            'notes' => 'nullable|string|max:1000',
        ];

        // Add manual payment field validation if payment method is manual
        if ($this->input('payment_method') === 'manual') {
            $manualSettings = $this->getManualGatewaySettings();
            
            // payer_name: required if require_payer_name is true (defaults to true)
            if (($manualSettings['require_payer_name'] ?? true) === true) {
                $rules['payer_name'] = 'required|string|max:255';
            } else {
                $rules['payer_name'] = 'nullable|string|max:255';
            }
            
            // payer_phone: required if require_payer_phone is true (defaults to false)
            if (($manualSettings['require_payer_phone'] ?? false) === true) {
                $rules['payer_phone'] = 'required|string|max:20';
            } else {
                $rules['payer_phone'] = 'nullable|string|max:20';
            }
            
            // account_details: required if require_account_details is true (defaults to false)
            if (($manualSettings['require_account_details'] ?? false) === true) {
                $rules['account_details'] = 'required|string|max:1000';
            } else {
                $rules['account_details'] = 'nullable|string|max:1000';
            }
            
            // payment_evidence: required array with min:1 if require_payment_evidence is true (defaults to true)
            if (($manualSettings['require_payment_evidence'] ?? true) === true) {
                $rules['payment_evidence'] = 'required|array|min:1';
                $rules['payment_evidence.*'] = 'required|string|url';
            } else {
                $rules['payment_evidence'] = 'nullable|array';
                $rules['payment_evidence.*'] = 'nullable|string|url';
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'payer_name.required' => 'Please provide the name of the person making the payment.',
            'payer_phone.required' => 'Please provide a contact phone number.',
            'account_details.required' => 'Please provide the account details used for payment.',
            'payment_evidence.required' => 'Please upload proof of payment.',
            'payment_evidence.min' => 'Please upload at least one payment evidence file.',
        ];
    }

    protected function getManualGatewaySettings(): array
    {
        // Cache for 1 hour to avoid repeated DB queries
        return Cache::remember('manual_gateway_settings', 3600, function () {
            $gateway = PaymentGateway::where('name', 'manual')
                ->where('is_active', true)
                ->first();
            
            return $gateway?->settings ?? [];
        });
    }
}