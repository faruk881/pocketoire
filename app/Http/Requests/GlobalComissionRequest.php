<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GlobalComissionRequest extends FormRequest
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
            'global_creator_commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'global_creator_commission_percent.required' => 'Creator commission percent is required.',
            'global_creator_commission_percent.numeric'  => 'Creator commission percent must be a number.',
            'global_creator_commission_percent.min'      => 'Creator commission percent must be at least 0%.',
            'global_creator_commission_percent.max'      => 'Creator commission percent may not be greater than 100%.',
        ];
    }
}
