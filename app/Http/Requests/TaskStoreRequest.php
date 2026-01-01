<?php

namespace App\Http\Requests;

use App\Enums\TaskComplexity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'complexity' => ['nullable', Rule::enum(TaskComplexity::class)],
            'duration' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'project_id' => ['nullable', 'exists:projects,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Please provide a title for the task.',
            'title.max' => 'The title must not exceed 255 characters.',
            'duration.min' => 'Duration must be at least 1 minute.',
            'end_date.after_or_equal' => 'The end date must be equal to or after the start date.',
            'project_id.exists' => 'The selected project does not exist.',
        ];
    }
}
