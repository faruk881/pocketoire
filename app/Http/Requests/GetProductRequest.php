<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetProductRequest extends FormRequest
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
            // 'product_link' => ['required', 'url', 'regex:/viator\.com\/.*\/(?:d\d+-|p-)[^\/\?]+/'],
                'product_link' => ['required','regex:/^https?:\/\/(.*viator\.com|.*expedia\.com)/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_link.required' => 'Product URL is required.',
            'product_link.url' => 'Please provide a valid URL.',
            'product_link.regex' => 'The URL must be a valid Viator product URL.',
        ];
    }
}
