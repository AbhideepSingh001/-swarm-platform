<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'in:low,medium,high,critical',
            'task_type' => 'in:code_generation,code_review,testing,documentation,research,data_processing,communication,custom',
            'payload' => 'nullable|array',
            'scheduled_at' => 'nullable|date',
            'deadline_at' => 'nullable|date|after:scheduled_at',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'max_retries' => 'integer|min:0|max:10',
            'auto_assign' => 'boolean',
            'subtasks' => 'nullable|array',
            'subtasks.*.title' => 'required_with:subtasks|string',
            'subtasks.*.type' => 'required_with:subtasks|string',
            'subtasks.*.priority' => 'in:low,medium,high,critical',
        ];
    }
}