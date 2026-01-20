<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactMessageRequest extends FormRequest
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
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255'],
            'message' => ['required','string','max:5000'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Name is required.',
            'name.string' => 'Name must be a valid text.',
            'name.max' => 'Name may not be greater than :max characters.',

            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.max' => 'Email may not be greater than :max characters.',

            'message.required' => 'Message is required.',
            'message.string' => 'Message must be a valid text.',
            'message.max' => 'Message may not be greater than :max characters.',
        ];
    }   
}
