<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorefrontUrlCheckRequest extends FormRequest
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
                'storeurl' => [
                'required',
                'string',
                'alpha_dash',
                'min:3',
                'max:150',
                'unique:storefronts,slug',
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'storeurl.required' => 'Store URL is required.',
            'storeurl.alpha_dash' => 'Store URL may only contain letters, numbers, dashes, and underscores.',
            'storeurl.unique' => 'This store URL is already taken. Please choose another one.',
            'storeurl.min' => 'Store URL must be at least 3 characters.',
            'storeurl.max' => 'Store URL cannot exceed 150 characters.'
        ];
    }
}
