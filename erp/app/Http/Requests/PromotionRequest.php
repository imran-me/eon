<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromotionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    // public function authorize(): bool
    // {
    //     return false;
    // }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],

            'new_designation_id' => [
                'required',
                'exists:designations,id',
                'different:previous_designation_id'
            ],

            'effective_from' => ['required', 'date'],
            'approved_at'    => ['nullable', 'date'],

            'new_salary' => ['nullable', 'numeric', 'min:0'],
            'reason'     => ['nullable', 'string', 'max:1000'],
        ];
    }
}
