<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchStorefrontRequest extends FormRequest
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
            'search'   => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort'     => 'nullable|string|in:popular,newest,oldest,name_asc,name_desc', 
        ];
    }

    public function messages(): array
    {
        return [
            'search.string'    => 'The search keyword must be valid text.',
            'search.max'       => 'Search keyword is too long. Please use less than 100 characters.',
            'per_page.integer' => 'Items per page must be a valid number.',
            'per_page.max'     => 'You can only retrieve up to 100 storefronts at a time.',
            'sort.in'        => 'Invalid filter. Available options: popular, newest, oldest, name_asc, name_desc.',
        ];
    }
}
