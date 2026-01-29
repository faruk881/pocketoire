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
            'album_id' => ['required', 'integer', 'exists:albums,id'],
            'product_link' => ['required', 'url', 'regex:/viator\.com\/.*\/(?:d\d+-|p-)[^\/\?]+/'],
        ];
    }

    public function messages(): array
    {
        return [
            // album_id messages
            'album_id.required' => 'Album is required.',
            'album_id.integer'  => 'Album ID must be a valid number.',
            'album_id.exists'   => 'The selected album does not exist.',

            // product_link messages
            'product_link.required' => 'Product link is required.',
            'product_link.url'      => 'Product link must be a valid URL.',
            'product_link.regex'    => 'Please provide a valid Viator product link.',
        ];
    }
}
