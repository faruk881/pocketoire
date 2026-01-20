<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreatorProfileRequest extends FormRequest
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
            
            'profile_photo' => [
                'sometimes','nullable','image','mimes:jpg,jpeg,png,webp','max:2048', // 2MB
            ],
            'cover_photo' => [
                'sometimes','nullable','image','mimes:jpg,jpeg,png,webp','max:4096', // 4MB
            ],
            'store_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'store_bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'storefront_url' => ['required', 'string', 'alpha_dash', 'min:3', 'max:150', 'unique:storefronts,slug'],
            'tiktok_link' => ['sometimes', 'nullable', 'url', 'max:255'],
            'instagram_link' => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_name.string' => 'Store name must be a valid text.',
            'store_name.max' => 'Store name may not be greater than :max characters.',

            'store_bio.string' => 'Store bio must be a valid text.',
            'store_bio.max' => 'Store bio may not be greater than :max characters.',

            'name.string' => 'Name must be a valid text.',
            'name.max' => 'Name may not be greater than :max characters.',

            'storefront_url.required' => 'Store URL is required.',
            'storefront_url.alpha_dash' => 'Store URL may only contain letters, numbers, dashes, and underscores.',
            'storefront_url.unique' => 'This store URL is already taken. Please choose another one.',
            'storefront_url.min' => 'Store URL must be at least 3 characters.',
            'storefront_url.max' => 'Store URL cannot exceed 150 characters.',
            'tiktok_link.url' => 'TikTok link must be a valid URL (include http:// or https://).',
            'tiktok_link.max' => 'TikTok link may not be greater than :max characters.',

            'instagram_link.url' => 'Instagram link must be a valid URL (include http:// or https://).',
            'instagram_link.max' => 'Instagram link may not be greater than :max characters.',

            'profile_photo.image' => 'Profile photo must be a valid image.',
            'profile_photo.mimes' => 'Profile photo must be a JPG, PNG, or WEBP image.',
            'profile_photo.max' => 'Profile photo size must not exceed 2MB.',

            'cover_photo.image' => 'Cover photo must be a valid image.',
            'cover_photo.mimes' => 'Cover photo must be a JPG, PNG, or WEBP image.',
            'cover_photo.max' => 'Cover photo size must not exceed 4MB.',
        ];
    }
}
