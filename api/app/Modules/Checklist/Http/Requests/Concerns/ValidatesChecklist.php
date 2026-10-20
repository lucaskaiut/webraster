<?php

namespace App\Modules\Checklist\Http\Requests\Concerns;

use App\Modules\Checklist\Enums\ChecklistAnswerType;
use App\Modules\Checklist\Enums\ChecklistPhotoRule;
use App\Modules\Checklist\Enums\ChecklistType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesChecklist
{
    /**
     * @return array<string, mixed>
     */
    protected function checklistRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(ChecklistType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'questions' => ['sometimes', 'array', 'max:100'],
            'questions.*.prompt' => ['required', 'string', 'max:1000'],
            'questions.*.answer_type' => ['required', Rule::enum(ChecklistAnswerType::class)],
            'questions.*.photo_rule' => ['required', Rule::enum(ChecklistPhotoRule::class)],
            'questions.*.options' => ['sometimes', 'array', 'max:50'],
            'questions.*.options.*.label' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $name = $this->input('name');

            if (is_string($name) && trim($name) === '') {
                $validator->errors()->add('name', 'Informe o nome do checklist.');
            }

            $questions = $this->input('questions');

            if (! is_array($questions)) {
                return;
            }

            foreach ($questions as $index => $question) {
                if (! is_array($question)) {
                    continue;
                }

                $prompt = $question['prompt'] ?? null;

                if (is_string($prompt) && trim($prompt) === '') {
                    $validator->errors()->add("questions.{$index}.prompt", 'Informe o enunciado.');
                }

                $options = is_array($question['options'] ?? null) ? $question['options'] : [];

                foreach ($options as $optionIndex => $option) {
                    $label = is_array($option) ? ($option['label'] ?? null) : null;

                    if (is_string($label) && trim($label) === '') {
                        $validator->errors()->add(
                            "questions.{$index}.options.{$optionIndex}.label",
                            'Informe o texto da opção.',
                        );
                    }
                }

                $answerType = $question['answer_type'] ?? null;

                if (
                    in_array($answerType, [
                        ChecklistAnswerType::SINGLE_CHOICE->value,
                        ChecklistAnswerType::MULTIPLE_CHOICE->value,
                    ], true)
                    && count($options) === 0
                ) {
                    $validator->errors()->add(
                        "questions.{$index}.options",
                        'Adicione ao menos uma opção para perguntas de seleção.',
                    );
                }
            }
        });
    }
}
