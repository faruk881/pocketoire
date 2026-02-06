<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'album_id'     => ['required', 'integer', 'exists:albums,id'],
            'product_url' => ['required', 'url', 'regex:/viator\.com\/.*\/(?:d\d+-|p-)[^\/\?]+/'],
            'product_name' => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:2000'],
            // 'price'        => ['required', 'numeric', 'min:0'],
            // 'currency'     => ['required', 'string', 'size:3'], // e.g., USD, EUR
            'image_url'    => ['nullable', 'url','required_without:image'],
            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096','required_without:image_url'],
        ];
    }

    public function messages(): array
    {
        return [
                // album_id messages
                'album_id.required' => 'Album is required.',
                'album_id.integer'  => 'Album ID must be a valid number.',
                'album_id.exists'   => 'The selected album does not exist.',

                // product_url messages
                'product_url.required' => 'Product link is required.',
                'product_url.url'      => 'Product link must be a valid URL.',
                'product_url.regex'    => 'Please provide a valid Viator product link.',

                // product_name messages
                'product_name.required' => 'The product name is required.',
                'product_name.string'   => 'The product name must be valid text.',
                'product_name.max'      => 'The product name cannot exceed 255 characters.',

                // price & currency messages
                // 'price.required'        => 'Please specify the price.',
                // 'price.numeric'         => 'The price must be a number.',
                // 'currency.required'     => 'Please provide a currency code.',
                // 'currency.size'         => 'The currency must be a 3-letter code (e.g., USD, EUR).',

                // image messages
                'image.image' => 'Cover photo must be a valid image.',
                'image.mimes' => 'Cover photo must be a JPG, PNG, or WEBP image.',
                'image.max'   => 'Cover photo size must not exceed 4MB.',

                'image_url.required_without' => 'Please provide an image URL or upload an image.',
                'image.required_without'     => 'Please provide an image URL or upload an image.',

                // image_url messages
                // 'image_url.url' => 'The image source link must be a valid URL.',
            ];
    }
}
