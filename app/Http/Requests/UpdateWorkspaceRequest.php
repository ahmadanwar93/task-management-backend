<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
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
            'sprint_enabled' => ['sometimes', 'boolean'],
            'sprint_duration' => [
                'required_if:sprint_enabled,true',
                'prohibited_if:sprint_enabled,false',
                'in:weekly,biweekly'
            ]
        ];
    }
}
