<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class addComissionToCreatorRequest extends FormRequest
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
            'affiliate_comission' => ['required', 'numeric', 'min:0'],
            'creator_comission_percent'   => ['required', 'numeric', 'between:0,100'],
        ];
    }

    
    public function messages(): array
    {
        return [
            'affiliate_comission.required' => 'Affiliate commission is required.',
            'affiliate_comission.numeric'  => 'Affiliate commission must be a number.',
            'affiliate_comission.min'      => 'Affiliate commission cannot be negative.',

            'creator_comission_percent.required'   => 'Creator Commission percent is required.',
            'creator_comission_percent.numeric'    => 'Creator Commission percent must be a number.',
            'creator_comission_percent.between'    => 'Creator Commission percent must be between 0 and 100.',
        ];
    }
}
