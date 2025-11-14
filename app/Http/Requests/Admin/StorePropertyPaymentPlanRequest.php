<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyPaymentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'uuid', 'exists:properties,id'],
            'member_id' => ['required', 'uuid', 'exists:members,id'],
            'interest_id' => ['nullable', 'uuid', 'exists:property_interests,id'],
            'funding_option' => ['nullable', 'string', 'in:cash,loan,mix,equity_wallet,mortgage,cooperative'],
            'selected_methods' => ['nullable', 'array', 'min:1', 'max:3'],
            'selected_methods.*' => ['string', 'in:cash,loan,mix,equity_wallet,mortgage,cooperative'],
            'mix_allocations' => ['nullable', 'array'],
            'mix_allocations.*' => ['numeric', 'min:0', 'max:100'],
            'configuration' => ['nullable', 'array'],
            'schedule' => ['nullable', 'array'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'remaining_balance' => ['nullable', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'metadata' => ['nullable', 'array'],
            'status' => ['nullable', 'in:draft,active,completed,cancelled'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mix_allocations') && is_array($this->mix_allocations)) {
            $clean = [];
            foreach ($this->mix_allocations as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                if (!is_numeric($value)) {
                    continue;
                }

                $clean[$key] = (float) $value;
            }

            $this->merge([
                'mix_allocations' => $clean,
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->all();
            $fundingOption = $data['funding_option'] ?? null;
            $selectedMethods = $data['selected_methods'] ?? [];
            $allocations = $data['mix_allocations'] ?? [];

            if ($fundingOption === 'mix') {
                if (empty($selectedMethods)) {
                    $validator->errors()->add('selected_methods', 'Please select the funding methods that make up this mix plan.');
                }

                if (empty($allocations)) {
                    $validator->errors()->add('mix_allocations', 'Please provide percentage allocations for each selected payment method.');
                }

                if (!empty($allocations)) {
                    $allocationKeys = array_keys($allocations);
                    $diff = array_diff($selectedMethods, $allocationKeys);
                    if (!empty($diff)) {
                        $validator->errors()->add('mix_allocations', 'Every selected method must have a percentage allocation.');
                    }

                    $extra = array_diff($allocationKeys, $selectedMethods);
                    if (!empty($extra)) {
                        $validator->errors()->add('mix_allocations', 'Only include allocations for the selected payment methods.');
                    }

                    $total = array_reduce($allocations, static function ($carry, $value) {
                        return $carry + (is_numeric($value) ? (float) $value : 0);
                    }, 0.0);

                    if (abs($total - 100.0) > 0.01) {
                        $validator->errors()->add('mix_allocations', 'The total allocation for mix funding must equal 100%.');
                    }

                    foreach ($allocations as $method => $percentage) {
                        if ($percentage <= 0) {
                            $validator->errors()->add('mix_allocations.' . $method, 'Each funding method must be assigned more than 0%.');
                        }
                    }
                }
            }
        });
    }
}



