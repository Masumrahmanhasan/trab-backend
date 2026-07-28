<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->store->owner_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }
}
