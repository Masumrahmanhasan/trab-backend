<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ManageSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('super-admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:upgrade,downgrade,cancel,resume,renew'],
            'plan_id' => ['required_if:action,upgrade,downgrade', 'integer', 'exists:plans,id'],
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
            'action.required' => 'The action field is required.',
            'action.in' => 'The action must be one of: upgrade, downgrade, cancel, resume, renew.',
            'plan_id.required_if' => 'The plan_id field is required when action is upgrade or downgrade.',
            'plan_id.exists' => 'The selected plan is invalid.',
        ];
    }
}
