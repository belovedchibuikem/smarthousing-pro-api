<?php

namespace App\Http\Requests\Auth;

use App\Services\RecaptchaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
            'recaptcha_token' => ['required', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $recaptchaService = app(RecaptchaService::class);
            $token = $this->input('recaptcha_token');
            $remoteIp = $this->ip();

            if (!$recaptchaService->verify($token, $remoteIp)) {
                $validator->errors()->add('recaptcha_token', 'reCAPTCHA verification failed. Please try again.');
            }
        });
    }
}