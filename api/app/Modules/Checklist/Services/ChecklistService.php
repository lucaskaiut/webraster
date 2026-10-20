<?php

namespace App\Modules\Checklist\Services;

use App\Modules\Checklist\Enums\ChecklistPhotoRule;
use App\Modules\Checklist\Enums\ChecklistType;
use App\Modules\Checklist\Enums\ServiceOrderChecklistStatus;
use App\Modules\Checklist\Models\Checklist;
use App\Modules\Checklist\Models\ServiceOrderChecklist;
use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\Shared\Support\QuerySort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChecklistService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Checklist::query()
            ->withCount('questions')
            ->when(filled($filters['search'] ?? null), fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('description', 'like', '%'.$filters['search'].'%')))
            ->when(filled($filters['type'] ?? null), fn ($query) => $query
                ->where('type', $filters['type']))
            ->when(array_key_exists('is_active', $filters), fn ($query) => $query
                ->where('is_active', (bool) $filters['is_active']));

        return QuerySort::apply($query, $filters['sort'] ?? null, ['name', 'type', 'is_active', 'created_at'], 'name:asc')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Checklist
    {
        return DB::transaction(function () use ($data): Checklist {
            $checklist = Checklist::query()->create($this->payload($data));
            $this->syncQuestions($checklist, is_array($data['questions'] ?? null) ? $data['questions'] : []);

            return $checklist->refresh()->load('questions.options');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Checklist $checklist, array $data): Checklist
    {
        return DB::transaction(function () use ($checklist, $data): Checklist {
            $checklist->fill($this->payload($data));
            $checklist->save();

            if (array_key_exists('questions', $data)) {
                $this->syncQuestions($checklist, is_array($data['questions']) ? $data['questions'] : []);
            }

            return $checklist->refresh()->load('questions.options');
        });
    }

    public function delete(Checklist $checklist): void
    {
        $checklist->delete();
    }

    /**
     * Cria as instâncias que ainda não existem para os modelos ativos.
     *
     * @return Collection<int, ServiceOrderChecklist>
     */
    public function ensureInstancesForOrder(ServiceOrder $order): Collection
    {
        // OS encerradas não recebem modelos ativados posteriormente.
        if ($order->status->isTerminal()) {
            return $order->checklists()->get();
        }

        $applicableTypes = $order->status === ServiceOrderStatus::OPEN
            ? [ChecklistType::INITIAL->value, ChecklistType::FINAL->value]
            : [ChecklistType::FINAL->value];

        $templates = Checklist::query()
            ->where('is_active', true)
            ->whereIn('type', $applicableTypes)
            ->with(['questions.options'])
            ->orderBy('id')
            ->get();

        $existingChecklistIds = $order->checklists()
            ->whereNotNull('checklist_id')
            ->pluck('checklist_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($templates as $template) {
            if (in_array((int) $template->getKey(), $existingChecklistIds, true)) {
                continue;
            }

            $this->createInstance($order, $template);
        }

        return $order->checklists()->with('questions.options')->get();
    }

    public function hasActiveInitialTemplates(): bool
    {
        return Checklist::query()
            ->where('is_active', true)
            ->where('type', ChecklistType::INITIAL->value)
            ->whereHas('questions')
            ->exists();
    }

    public function assertReadyToStart(ServiceOrder $order): void
    {
        $this->assertReady($order, ChecklistType::INITIAL);
    }

    public function assertReadyToComplete(ServiceOrder $order): void
    {
        $this->assertReady($order, ChecklistType::FINAL);
    }

    private function assertReady(ServiceOrder $order, ChecklistType $type): void
    {
        $this->ensureInstancesForOrder($order);

        $instances = $order->checklists()
            ->where('type', $type->value)
            ->with(['questions.answer.photos'])
            ->orderBy('id')
            ->get();

        $pending = $instances
            ->where('status', ServiceOrderChecklistStatus::PENDING);

        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages([
                'checklists' => [sprintf(
                    $type === ChecklistType::INITIAL
                        ? 'Preencha o checklist inicial antes de iniciar o atendimento: %s.'
                        : 'Preencha o checklist final antes de concluir a OS: %s.',
                    $pending->pluck('name')->implode(', '),
                )],
            ]);
        }

        $invalid = $instances->filter(fn (ServiceOrderChecklist $instance) => $this->hasInvalidPhotos($instance));

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'checklists' => [sprintf(
                    'Corrija as fotografias pendentes do checklist: %s.',
                    $invalid->pluck('name')->implode(', '),
                )],
            ]);
        }
    }

    private function hasInvalidPhotos(ServiceOrderChecklist $instance): bool
    {
        foreach ($instance->questions as $question) {
            $photoCount = $question->answer?->photos?->count() ?? 0;

            if ($question->photo_rule === ChecklistPhotoRule::REQUIRED && $photoCount === 0) {
                return true;
            }

            if ($question->photo_rule === ChecklistPhotoRule::NONE && $photoCount > 0) {
                return true;
            }
        }

        return false;
    }

    private function createInstance(ServiceOrder $order, Checklist $template): ServiceOrderChecklist
    {
        // Checklists sem perguntas não bloqueiam o fluxo do técnico.
        $isEmpty = $template->questions->isEmpty();

        $instance = $order->checklists()->create([
            'tenant_id' => $order->tenant_id,
            'checklist_id' => $template->getKey(),
            'type' => $template->type,
            'name' => $template->name,
            'description' => $template->description,
            'status' => $isEmpty
                ? ServiceOrderChecklistStatus::COMPLETED
                : ServiceOrderChecklistStatus::PENDING,
            'completed_at' => $isEmpty ? now() : null,
        ]);

        foreach ($template->questions as $questionPosition => $question) {
            $instanceQuestion = $instance->questions()->create([
                'tenant_id' => $order->tenant_id,
                'checklist_question_id' => $question->getKey(),
                'prompt' => $question->prompt,
                'answer_type' => $question->answer_type,
                'photo_rule' => $question->photo_rule,
                'position' => $questionPosition,
            ]);

            foreach ($question->options as $optionPosition => $option) {
                $instanceQuestion->options()->create([
                    'tenant_id' => $order->tenant_id,
                    'checklist_question_option_id' => $option->getKey(),
                    'label' => $option->label,
                    'position' => $optionPosition,
                ]);
            }
        }

        $instance->unsetRelation('questions');

        return $instance;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $payload = Arr::only($data, ['name', 'description', 'type', 'is_active']);

        if (array_key_exists('name', $payload)) {
            $payload['name'] = trim((string) $payload['name']);
        }

        if (array_key_exists('description', $payload)) {
            $payload['description'] = filled($payload['description']) ? trim((string) $payload['description']) : null;
        }

        return $payload;
    }

    /**
     * Substitui as perguntas do modelo. A ordem do array define a posição.
     *
     * @param  list<array<string, mixed>>  $questions
     */
    private function syncQuestions(Checklist $checklist, array $questions): void
    {
        $checklist->questions()->delete();

        foreach (array_values($questions) as $position => $question) {
            $created = $checklist->questions()->create([
                'tenant_id' => $checklist->tenant_id,
                'prompt' => trim((string) ($question['prompt'] ?? '')),
                'answer_type' => $question['answer_type'],
                'photo_rule' => $question['photo_rule'],
                'position' => $position,
            ]);

            if (($question['answer_type'] ?? null) === 'text') {
                continue;
            }

            foreach (array_values(is_array($question['options'] ?? null) ? $question['options'] : []) as $optionPosition => $option) {
                $created->options()->create([
                    'tenant_id' => $checklist->tenant_id,
                    'label' => trim((string) ($option['label'] ?? '')),
                    'position' => $optionPosition,
                ]);
            }
        }

        $checklist->unsetRelation('questions');
    }
}
