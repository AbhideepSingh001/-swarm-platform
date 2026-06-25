<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'status' => 'sometimes|in:draft,pending,assigned,in_progress,blocked,review,completed,cancelled,failed',
            'payload' => 'nullable|array',
            'result' => 'nullable|array',
            'scheduled_at' => 'nullable|date',
            'deadline_at' => 'nullable|date',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
            'progress_percent' => 'integer|min:0|max:100',
        ];
    }
}