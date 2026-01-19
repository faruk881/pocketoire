<?php

namespace App\Http\Requests;

use Faker\Guesser\Name;
use Illuminate\Foundation\Http\FormRequest;

class CreateAlbumRequest extends FormRequest
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
            'name' => [
            'required',
            'string',
            'min:3',
            'max:100',
        ],
            'description' => [
            'nullable',
            'string',
            'max:1000',
        ],
        ];
    }

    public function messages(): array
    {
        return [
            'storename.required' => 'Store name is required.',
            'storename.min' => 'Store name must be at least 3 characters.',
            'storename.max' => 'Store name cannot exceed 100 characters.',
            
            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }
}
