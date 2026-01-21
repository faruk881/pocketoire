<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreatorStorefrontStatusRequest extends FormRequest
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
            'status' => 'required|in:pending,approved,rejected,banned',
            'status_reason' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'The status field is required.',
            'status.in' => 'The selected status is invalid. Allowed values are: pending, approved, rejected, banned.',
            'status_reason.string' => 'The status reason must be a string.',
            'status_reason.max' => 'The status reason may not be greater than 1000 characters.',
        ];
    }
}
