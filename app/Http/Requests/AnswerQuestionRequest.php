<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AnswerQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assessments.take') ?? false;
    }

    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer'],
            'option_id' => ['nullable', 'integer'],
            'confidence' => ['nullable', 'integer', 'between:1,4'],
            'time_ms' => ['nullable', 'integer', 'between:0,3600000'],
        ];
    }
}
