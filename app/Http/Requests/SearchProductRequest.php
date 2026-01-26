<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchProductRequest extends FormRequest
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
            'search'    => 'nullable|string|max:100',
            'per_page'  => 'nullable|integer|min:1|max:100',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0|gte:min_price', // Must be greater than or equal to min_price
            'sort'      => 'nullable|string|in:price_low,price_high,newest,oldest,title_asc,title_desc',
        ];
    }

    public function messages(): array
    {
        return [
            'search.max'      => 'Search keyword is too long. Please use less than 100 characters.',
            'min_price.numeric' => 'Minimum price must be a valid number.',
            'min_price.min'     => 'Price cannot be negative.',
            'max_price.numeric' => 'Maximum price must be a valid number.',
            'max_price.gte'     => 'Maximum price must be higher than the minimum price.',
            'sort.in'         => 'Invalid sort option. Try: price_low, price_high, newest, oldest, title_asc, or title_desc.',
        ];
    }
}
