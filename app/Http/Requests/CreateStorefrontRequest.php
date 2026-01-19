<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateStorefrontRequest extends FormRequest
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
            'storename' => [
                'required',
                'string',
                'min:3',
                'max:100',
            ],

            'storeurl' => [
                'required',
                'string',
                'alpha_dash',
                'min:3',
                'max:150',
                'unique:storefronts,slug',
            ],

            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // 2MB
            ],

            'cover_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096', // 4MB
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

            'storeurl.required' => 'Store URL is required.',
            'storeurl.alpha_dash' => 'Store URL may only contain letters, numbers, dashes, and underscores.',
            'storeurl.unique' => 'This store URL is already taken. Please choose another one.',
            'storeurl.min' => 'Store URL must be at least 3 characters.',
            'storeurl.max' => 'Store URL cannot exceed 150 characters.',

            'profile_photo.image' => 'Profile photo must be a valid image.',
            'profile_photo.mimes' => 'Profile photo must be a JPG, PNG, or WEBP image.',
            'profile_photo.max' => 'Profile photo size must not exceed 2MB.',

            'cover_photo.image' => 'Cover photo must be a valid image.',
            'cover_photo.mimes' => 'Cover photo must be a JPG, PNG, or WEBP image.',
            'cover_photo.max' => 'Cover photo size must not exceed 4MB.',

            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }
}
