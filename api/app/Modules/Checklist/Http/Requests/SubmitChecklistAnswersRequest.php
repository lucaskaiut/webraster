<?php

namespace App\Modules\Checklist\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitChecklistAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array', 'max:100'],
            'answers.*.question_id' => ['required', 'string', 'max:64'],
            'answers.*.text_answer' => ['nullable', 'string', 'max:5000'],
            'answers.*.option_ids' => ['sometimes', 'array', 'max:50'],
            'answers.*.option_ids.*' => ['string', 'max:64'],
        ];
    }
}
