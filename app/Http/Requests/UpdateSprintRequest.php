<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSprintRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'start_date' => [
                'sometimes',
                'date',
            ],
            'end_date' => [
                'sometimes',
                'nullable',
                'date',
                'after:start_date',
            ],
            'status' => ['sometimes', 'in:planned,active,completed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'The sprint name cannot exceed 255 characters.',
            'start_date.after_or_equal' => 'The start date must be today or a future date.',
            'end_date.after' => 'The end date must be after the start date.',
            'status.in' => 'The status must be one of: planned, active, or completed.',
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'start date',
            'end_date' => 'end date',
        ];
    }
}
