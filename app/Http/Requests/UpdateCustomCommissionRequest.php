<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomCommissionRequest extends FormRequest
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
            'creator_commission_percent' => 'required|numeric|min:0|max:100',
            'effective_from'             => 'required|date',
            'effective_to'               => 'nullable|date|after_or_equal:effective_from',
        ];
    }

    public function messages(): array
    {
        return [
            'creator_commission_percent.required' => 'Creator Commission percent is required.',
            'creator_commission_percent.numeric'  => 'Creator Commission percent must be a number.',
            'creator_commission_percent.min'      => 'Creator Commission percent must be between 0 and 100.',
            'creator_commission_percent.max'      => 'Creator Commission percent must be between 0 and 100.',
            'effective_from.required'             => 'Effective from date is required.',
            'effective_from.date'                 => 'Effective from must be a valid date.',
            'effective_to.date'                   => 'Effective to must be a valid date.',
            'effective_to.after_or_equal'         => 'Effective to must be a date after or equal to Effective from.',
        ];
    }
}
