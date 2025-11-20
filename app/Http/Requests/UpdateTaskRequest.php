<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
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
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:backlog,todo,in_progress,done',
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'sprint_id' => 'nullable|exists:sprints,id',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Task title is required',
            'title.max' => 'Task title must not exceed 255 characters',
            'status.in' => 'Invalid status. Must be: backlog, todo, in_progress, or done',
            'assigned_to.exists' => 'Assigned user does not exist',
            'sprint_id.exists' => 'Sprint does not exist',
        ];
    }
}
