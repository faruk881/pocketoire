<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class addcommissionToCreatorRequest extends FormRequest
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
            'platform_commission' => ['required', 'numeric', 'min:0'],
            'creator_commission_percent'   => ['required', 'numeric', 'between:0,100'],
        ];
    }

    
    public function messages(): array
    {
        return [
            'platform_commission.required' => 'Platform commission is required.',
            'platform_commission.numeric'  => 'Platform commission must be a number.',
            'platform_commission.min'      => 'Platform commission cannot be negative.',

            'creator_commission_percent.required'   => 'Creator Commission percent is required.',
            'creator_commission_percent.numeric'    => 'Creator Commission percent must be a number.',
            'creator_commission_percent.between'    => 'Creator Commission percent must be between 0 and 100.',
        ];
    }
}
