<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StripeOnboardRequest extends FormRequest
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
            'country' => 'required|string|size:2'
        ];
    }

    public function messages(): array
    {
        return [
            'country.required' => 'Country is required.',
            'country.string'   => 'Country must be a string.',
            'country.size'     => 'Country must be a valid 2-letter country code.',
        ];
    }
}
