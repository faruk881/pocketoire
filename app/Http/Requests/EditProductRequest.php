<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditProductRequest extends FormRequest
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
            'image_url'    => ['nullable', 'url'],
            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function message(): array
    {
        return [
            'image.image' => 'Cover photo must be a valid image.',
            'image.mimes' => 'Cover photo must be a JPG, PNG, or WEBP image.',
            'image.max'   => 'Cover photo size must not exceed 4MB.',
        ];
    }
}
