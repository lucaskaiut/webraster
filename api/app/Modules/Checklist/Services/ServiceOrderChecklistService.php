<?php

namespace App\Modules\Checklist\Services;

use App\Modules\Checklist\Enums\ChecklistAnswerType;
use App\Modules\Checklist\Enums\ChecklistPhotoRule;
use App\Modules\Checklist\Enums\ChecklistType;
use App\Modules\Checklist\Enums\ServiceOrderChecklistStatus;
use App\Modules\Checklist\Models\ServiceOrderChecklist;
use App\Modules\Checklist\Models\ServiceOrderChecklistAnswer;
use App\Modules\Checklist\Models\ServiceOrderChecklistPhoto;
use App\Modules\Checklist\Models\ServiceOrderChecklistQuestion;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceOrderChecklistService
{
    public const PHOTO_DISK = 'local';

    public const MAX_PHOTO_KILOBYTES = 10240;

    /**
     * Salva as respostas de um checklist e o marca como concluído.
     *
     * @param  list<array<string, mixed>>  $answers
     */
    public function submit(
        ServiceOrder $order,
        ServiceOrderChecklist $checklist,
        array $answers,
        User $actor,
    ): ServiceOrderChecklist {
        $this->assertBelongs($order, $checklist);
        $this->assertOrderMutable($order);
        $this->assertFillingAllowed($order, $checklist);

        $checklist->load(['questions.options', 'questions.answer.photos']);

        $normalized = $this->validateAnswers($checklist, $answers);

        DB::transaction(function () use ($order, $checklist, $normalized, $actor): void {
            foreach ($checklist->questions as $question) {
                $answer = $normalized[$question->getKey()];
                $optionIds = $answer['option_ids'];

                $record = ServiceOrderChecklistAnswer::query()->updateOrCreate(
                    ['service_order_checklist_question_id' => $question->getKey()],
                    [
                        'tenant_id' => $order->tenant_id,
                        'text_answer' => $answer['text_answer'],
                        'answered_at' => now(),
                        'answered_by' => $actor->getKey(),
                    ],
                );

                $record->selectedOptions()->sync($optionIds);
            }

            $checklist->forceFill([
                'status' => ServiceOrderChecklistStatus::COMPLETED,
                'completed_at' => now(),
                'completed_by' => $actor->getKey(),
            ])->save();
        });

        return $checklist->refresh()->load($this->detailRelations());
    }

    public function addPhoto(
        ServiceOrder $order,
        ServiceOrderChecklist $checklist,
        ServiceOrderChecklistQuestion $question,
        UploadedFile $file,
        User $actor,
    ): ServiceOrderChecklistPhoto {
        $this->assertBelongs($order, $checklist);
        $this->assertQuestionBelongs($checklist, $question);
        $this->assertOrderMutable($order);
        $this->assertFillingAllowed($order, $checklist);

        if ($question->photo_rule === ChecklistPhotoRule::NONE) {
            throw ValidationException::withMessages([
                'file' => ['Esta pergunta não permite anexar fotografias.'],
            ]);
        }

        $answer = ServiceOrderChecklistAnswer::query()->firstOrCreate(
            ['service_order_checklist_question_id' => $question->getKey()],
            ['tenant_id' => $order->tenant_id],
        );

        $path = $file->store(
            "service-orders/{$order->uuid}/checklists/{$checklist->uuid}/questions/{$question->uuid}",
            self::PHOTO_DISK,
        );

        if ($path === false) {
            throw ValidationException::withMessages(['file' => ['Não foi possível salvar a fotografia.']]);
        }

        return $answer->photos()->create([
            'tenant_id' => $order->tenant_id,
            'disk' => self::PHOTO_DISK,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName() ?: basename($path), 0, 255),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => $actor->getKey(),
        ]);
    }

    public function deletePhoto(
        ServiceOrder $order,
        ServiceOrderChecklist $checklist,
        ServiceOrderChecklistQuestion $question,
        ServiceOrderChecklistPhoto $photo,
    ): void {
        $this->assertBelongs($order, $checklist);
        $this->assertQuestionBelongs($checklist, $question);
        $this->assertPhotoBelongs($question, $photo);
        $this->assertOrderMutable($order);
        $this->assertFillingAllowed($order, $checklist);

        Storage::disk($photo->disk)->delete($photo->path);
        $photo->delete();
    }

    public function downloadPhotoForManager(
        ServiceOrder $order,
        ServiceOrderChecklist $checklist,
        ServiceOrderChecklistQuestion $question,
        ServiceOrderChecklistPhoto $photo,
    ): StreamedResponse {
        $this->assertBelongs($order, $checklist);
        $this->assertQuestionBelongs($checklist, $question);
        $this->assertPhotoBelongs($question, $photo);

        $disk = Storage::disk($photo->disk);

        if (! $disk->exists($photo->path)) {
            throw (new ModelNotFoundException)->setModel(ServiceOrderChecklistPhoto::class, [$photo->uuid]);
        }

        return $disk->response($photo->path, $photo->original_name, array_filter([
            'Content-Type' => $photo->mime_type,
        ]), 'inline');
    }

    public function downloadPhoto(ServiceOrderChecklistPhoto $photo, User $actor): StreamedResponse
    {
        $photo->loadMissing('answer.question.checklist.serviceOrder');

        $order = $photo->answer?->question?->checklist?->serviceOrder;

        if (
            $order === null
            || (int) $order->technician_id !== (int) $actor->getKey()
        ) {
            throw (new ModelNotFoundException)->setModel(ServiceOrderChecklistPhoto::class, [$photo->uuid]);
        }

        $disk = Storage::disk($photo->disk);

        if (! $disk->exists($photo->path)) {
            throw (new ModelNotFoundException)->setModel(ServiceOrderChecklistPhoto::class, [$photo->uuid]);
        }

        return $disk->response($photo->path, $photo->original_name, array_filter([
            'Content-Type' => $photo->mime_type,
        ]), 'inline');
    }

    /**
     * @return list<string>
     */
    public function detailRelations(): array
    {
        return [
            'questions.options',
            'questions.answer.selectedOptions',
            'questions.answer.photos',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     * @return array<int, array{text_answer: ?string, option_ids: list<int>}>
     */
    private function validateAnswers(ServiceOrderChecklist $checklist, array $answers): array
    {
        $indexed = [];

        foreach (array_values($answers) as $index => $answer) {
            $questionId = $answer['question_id'] ?? null;

            if (! is_string($questionId) || isset($indexed[$questionId])) {
                throw ValidationException::withMessages([
                    "answers.{$index}.question_id" => ['Pergunta inválida ou repetida.'],
                ]);
            }

            $indexed[$questionId] = [$index, $answer];
        }

        $knownQuestionIds = $checklist->questions->pluck('uuid')->all();
        $unknown = array_diff(array_keys($indexed), $knownQuestionIds);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'answers' => ['Foi enviada uma resposta para uma pergunta que não pertence a este checklist.'],
            ]);
        }

        $normalized = [];
        $errors = [];

        foreach ($checklist->questions as $index => $question) {
            [$inputIndex, $input] = $indexed[$question->uuid] ?? [null, null];

            if ($input === null) {
                $errors["answers.{$index}"] = ['Responda esta pergunta.'];

                continue;
            }

            $textAnswer = null;
            $optionIds = [];

            if ($question->answer_type === ChecklistAnswerType::TEXT) {
                $textAnswer = trim((string) ($input['text_answer'] ?? ''));

                if ($textAnswer === '') {
                    $errors["answers.{$inputIndex}.text_answer"] = ['Informe uma resposta.'];

                    continue;
                }
            } else {
                $submittedOptions = array_values(array_unique(array_filter(
                    is_array($input['option_ids'] ?? null) ? $input['option_ids'] : [],
                    fn ($id) => is_string($id) && $id !== '',
                )));

                $expected = $question->answer_type === ChecklistAnswerType::SINGLE_CHOICE ? 1 : null;

                if ($expected !== null && count($submittedOptions) !== 1) {
                    $errors["answers.{$inputIndex}.option_ids"] = ['Selecione uma opção.'];
                } elseif ($expected === null && $submittedOptions === []) {
                    $errors["answers.{$inputIndex}.option_ids"] = ['Selecione ao menos uma opção.'];
                } else {
                    $available = $question->options->pluck('id', 'uuid');
                    $resolved = [];

                    foreach ($submittedOptions as $optionUuid) {
                        $optionId = $available->get($optionUuid);

                        if ($optionId === null) {
                            $errors["answers.{$inputIndex}.option_ids"] = ['Selecione apenas opções válidas.'];
                            break;
                        }

                        $resolved[] = (int) $optionId;
                    }

                    $optionIds = array_values(array_unique($resolved));
                }
            }

            $photoCount = $question->answer?->photos?->count() ?? 0;

            if ($question->photo_rule === ChecklistPhotoRule::REQUIRED && $photoCount === 0) {
                $errors["answers.{$inputIndex}.photos"] = ['Anexe ao menos uma fotografia.'];
            }

            if ($question->photo_rule === ChecklistPhotoRule::NONE && $photoCount > 0) {
                $errors["answers.{$inputIndex}.photos"] = ['Esta pergunta não permite fotografias.'];
            }

            $normalized[$question->getKey()] = [
                'text_answer' => $textAnswer,
                'option_ids' => $optionIds,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    private function assertBelongs(ServiceOrder $order, ServiceOrderChecklist $checklist): void
    {
        if ((int) $checklist->service_order_id !== (int) $order->getKey()) {
            throw (new ModelNotFoundException)->setModel(ServiceOrderChecklist::class, [$checklist->uuid]);
        }
    }

    private function assertQuestionBelongs(
        ServiceOrderChecklist $checklist,
        ServiceOrderChecklistQuestion $question,
    ): void {
        if ((int) $question->service_order_checklist_id !== (int) $checklist->getKey()) {
            throw (new ModelNotFoundException)->setModel(ServiceOrderChecklistQuestion::class, [$question->uuid]);
        }
    }

    private function assertPhotoBelongs(
        ServiceOrderChecklistQuestion $question,
        ServiceOrderChecklistPhoto $photo,
    ): void {
        if ((int) $photo->answer?->service_order_checklist_question_id !== (int) $question->getKey()) {
            throw (new ModelNotFoundException)->setModel(ServiceOrderChecklistPhoto::class, [$photo->uuid]);
        }
    }

    private function assertOrderMutable(ServiceOrder $order): void
    {
        if (in_array($order->status, [ServiceOrderStatus::COMPLETED, ServiceOrderStatus::CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Esta OS está encerrada e não permite alterações no checklist.'],
            ]);
        }
    }

    private function assertFillingAllowed(ServiceOrder $order, ServiceOrderChecklist $checklist): void
    {
        $allowed = match ($checklist->type) {
            ChecklistType::INITIAL => $order->status === ServiceOrderStatus::OPEN,
            ChecklistType::FINAL => $order->status === ServiceOrderStatus::IN_PROGRESS,
        };

        if (! $allowed) {
            throw ValidationException::withMessages([
                'status' => [
                    $checklist->type === ChecklistType::INITIAL
                        ? 'O checklist inicial deve ser preenchido antes de iniciar a OS.'
                        : 'O checklist final deve ser preenchido com a OS em andamento.',
                ],
            ]);
        }
    }
}
