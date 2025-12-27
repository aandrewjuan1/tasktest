<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventStoreRequest extends FormRequest
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
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'all_day' => ['boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
            'status' => ['nullable', Rule::enum(EventStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Please provide a title for the event.',
            'title.max' => 'The title must not exceed 255 characters.',
            'start_datetime.required' => 'Please specify when the event starts.',
            'end_datetime.required' => 'Please specify when the event ends.',
            'end_datetime.after' => 'The end time must be after the start time.',
            'location.max' => 'The location must not exceed 255 characters.',
            'color.max' => 'Invalid color format.',
        ];
    }
}
