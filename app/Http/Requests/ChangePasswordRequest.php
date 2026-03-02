<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
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
            'currentPassword' => ['required','string'],
            'newPassword' => ['required','string','min:8'],
            'confirmPassword' => ['required','same:newPassword'],
        ];
    }

    public function messages()
    {
        return[
            'currentPassword.required' => 'Current password is required',
            'new_password.required' => 'New password is required',
            'new_password.min' => 'New password must be at least 8 characters long',
            'new_password.confirmed' => 'New password confirmation does not match'
        ];
    }
}
