<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // we are creating workspace, the role is in the pivot which we havent created yet, hence the return true
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
            'name' => ['required', 'string', 'max:255'],
            'sprint_enabled' => ['required', 'boolean'],
            'sprint_duration' => [
                'nullable',
                'required_if:sprint_enabled,true',
                'in:weekly,biweekly'
            ],
        ];
    }
}
