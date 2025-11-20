<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSprintRequest extends FormRequest
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
            'workspace_id' => ['required', 'exists:workspaces,id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => [
                'nullable',
                'date',
                'after:start_date',
                'required_if:is_eternal,false', // end_date required if not eternal
            ],
            'status' => ['sometimes', 'in:planned,active,completed'],
            'is_eternal' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'workspace_id.required' => 'The workspace is required.',
            'workspace_id.exists' => 'The selected workspace does not exist.',
            'name.required' => 'The sprint name is required.',
            'name.max' => 'The sprint name cannot exceed 255 characters.',
            'start_date.required' => 'The start date is required.',
            'start_date.after_or_equal' => 'The start date must be today or a future date.',
            'end_date.after' => 'The end date must be after the start date.',
            'end_date.required_if' => 'The end date is required for non-eternal sprints.',
            'end_date.prohibited_if' => 'Eternal sprints cannot have an end date.',
            'status.in' => 'The status must be one of: planned, active, or completed.',
            'is_eternal.required' => 'Please specify if this is an eternal sprint.',
        ];
    }

    public function attributes(): array
    {
        // For more readable error message
        return [
            'workspace_id' => 'workspace',
            'is_eternal' => 'eternal sprint',
        ];
    }
}
