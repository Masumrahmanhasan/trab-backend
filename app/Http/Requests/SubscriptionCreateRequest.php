<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_gateway_id' => ['nullable', 'string', 'max:100'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'The plan field is required.',
            'plan_id.exists' => 'The selected plan is invalid.',
            'store_id.exists' => 'The selected store is invalid.',
            'payment_method.max' => 'The payment method may not be greater than 50 characters.',
            'payment_gateway_id.max' => 'The payment gateway ID may not be greater than 100 characters.',
            'trial_days.min' => 'The trial days must be at least 0.',
            'starts_at.date' => 'The start date must be a valid date.',
        ];
    }
}
