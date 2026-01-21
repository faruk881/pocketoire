<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserStatusRequest extends FormRequest
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
                      
            'status' => 'required|in:active,suspended,banned',
            'status_reason' => 'required_if:status,suspended,banned|string|max:255',

        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'The status field is required.',
            'status.in' => 'The selected status is invalid. Allowed values are active, suspended, banned.',
            'status_reason.required_if' => 'The status reason is required when status is suspended or banned.',
            'status_reason.string' => 'The status reason must be a string.',
            'status_reason.max' => 'The status reason may not be greater than 255 characters.',
        ];
    }
}
