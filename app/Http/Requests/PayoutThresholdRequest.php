<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayoutThresholdRequest extends FormRequest
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
            'minimum_amount' => ['required', 'numeric', 'min:0'],
            'maximum_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'minimum_amount.required' => 'Minimum amount is required.',
            'minimum_amount.numeric' => 'Minimum amount must be a number.',
            'minimum_amount.min' => 'Minimum amount must be at least 0.',
            'maximum_amount.required' => 'Maximum amount is required.',
            'maximum_amount.numeric' => 'Maximum amount must be a number.',
            'maximum_amount.min' => 'Maximum amount must be at least 0.',
        ];
    }
}
