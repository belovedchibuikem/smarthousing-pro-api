<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyPaymentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
}



