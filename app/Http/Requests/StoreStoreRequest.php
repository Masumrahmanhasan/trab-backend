<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:stores,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
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
            'name.required' => 'The store name field is required.',
            'name.max' => 'The store name may not be greater than 255 characters.',
            'slug.required' => 'The slug field is required.',
            'slug.unique' => 'The slug has already been taken.',
            'slug.max' => 'The slug may not be greater than 255 characters.',
            'description.max' => 'The description may not be greater than 1000 characters.',
            'plan_id.required' => 'The plan field is required.',
            'plan_id.exists' => 'The selected plan is invalid.',
        ];
    }
}
