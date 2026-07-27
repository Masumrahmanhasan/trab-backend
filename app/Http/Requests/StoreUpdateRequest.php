<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $store = $this->route('store');

        return $this->user() !== null && $store->owner_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $store = $this->route('store');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:stores,slug,'.$store->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'plan_id' => ['sometimes', 'integer', 'exists:plans,id'],
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
            'name.max' => 'The store name may not be greater than 255 characters.',
            'slug.unique' => 'The slug has already been taken.',
            'slug.max' => 'The slug may not be greater than 255 characters.',
            'description.max' => 'The description may not be greater than 1000 characters.',
            'plan_id.exists' => 'The selected plan is invalid.',
        ];
    }
}
