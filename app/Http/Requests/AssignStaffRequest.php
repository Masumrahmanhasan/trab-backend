<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $store = $this->route('store');

        if (! $user || ! $store) {
            return false;
        }

        return $user->id === $store->owner_id || $user->hasRole('super-admin');
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
