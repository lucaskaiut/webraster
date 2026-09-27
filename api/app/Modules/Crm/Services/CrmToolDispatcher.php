<?php

namespace App\Modules\Crm\Services;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Crm\Models\AiToolExecution;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\PipelineStage;
use App\Modules\Crm\Support\CrmAiPipelineContext;
use App\Modules\Crm\Support\LeadNotesAppender;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CrmToolDispatcher
{
    public function __construct(
        private readonly LeadService $leads,
        private readonly CrmAiPipelineContext $pipelineContext,
        private readonly LeadNotesAppender $noteAppender,
        private readonly CrmAiHandoffService $handoff,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function dispatch(
        string $tool,
        array $arguments,
        Lead $lead,
        ?Conversation $conversation = null,
        ?array $allowedActions = null,
    ): array {
        $allowed = $allowedActions ?? ['move_lead', 'get_lead', 'add_lead_note', 'request_human_handoff'];

        if (! in_array($tool, $allowed, true)) {
            throw ValidationException::withMessages([
                'tool' => ["Ferramenta [{$tool}] não permitida nesta etapa."],
            ]);
        }

        $started = microtime(true);

        try {
            $result = match ($tool) {
                'move_lead' => $this->moveLead($lead, $arguments),
                'get_lead' => $this->getLead($lead),
                'add_lead_note' => $this->addNote($lead, $arguments),
                'request_human_handoff' => $this->requestHumanHandoff($lead, $conversation, $arguments),
                default => throw ValidationException::withMessages([
                    'tool' => ["Ferramenta desconhecida: {$tool}."],
                ]),
            };

            if (($result['ok'] ?? true) === false) {
                $this->audit($tool, $arguments, $result, 'failed', $lead, $conversation, $started, (string) ($result['error'] ?? ''));

                return $result;
            }

            $this->audit($tool, $arguments, $result, 'success', $lead, $conversation, $started);

            return $result;
        } catch (\Throwable $e) {
            $this->audit($tool, $arguments, null, 'failed', $lead, $conversation, $started, $e->getMessage());

            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'move_lead',
                    'description' => 'Obrigatório para mudar etapa no kanban. Use stage_id (UUID ou nome exato). add_lead_note não move o lead.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'stage_id' => ['type' => 'string', 'description' => 'UUID (preferencial) ou nome exato da etapa de destino'],
                        ],
                        'required' => ['stage_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_lead',
                    'description' => 'Consulta dados resumidos do lead atual.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_lead_note',
                    'description' => 'Registra apenas fatos NOVOS desta interação (1–3 frases). Não repita o campo notes de get_lead nem reescreva o perfil completo.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'note' => ['type' => 'string', 'description' => 'Somente informação nova aprendida agora (ex.: preferência de horário, pedido de demo).'],
                        ],
                        'required' => ['note'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'request_human_handoff',
                    'description' => 'Encaminha a conversa para atendimento humano quando não souber responder, o cliente pedir uma pessoa, ou houver limitação técnica/comercial.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'description' => 'Motivo interno (para a equipe), em português.',
                            ],
                            'message_to_customer' => [
                                'type' => 'string',
                                'description' => 'Mensagem cordial ao cliente informando que um humano assumirá (opcional se já respondeu no chat).',
                            ],
                        ],
                        'required' => ['reason'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function moveLead(Lead $lead, array $arguments): array
    {
        $stageRef = trim((string) ($arguments['stage_id'] ?? ''));

        if ($stageRef === '') {
            return $this->moveLeadError($lead, 'Informe stage_id (UUID ou nome da etapa).');
        }

        $stage = PipelineStage::query()
            ->where('pipeline_id', $lead->pipeline_id)
            ->where('active', true)
            ->where(function ($query) use ($stageRef): void {
                $query->where('uuid', $stageRef)->orWhere('name', $stageRef);
            })
            ->first();

        if ($stage === null) {
            return $this->moveLeadError($lead, 'Etapa não encontrada neste funil. Use o UUID de available_stages.');
        }

        $updated = $this->leads->moveToStage($lead, $stage);

        return [
            'ok' => true,
            'lead_id' => $updated->uuid,
            'stage_id' => $updated->stage?->uuid,
            'stage_name' => $updated->stage?->name,
        ];
    }

    /**
     * @return array{ok: false, error: string, available_stages: list<array<string, mixed>>}
     */
    private function moveLeadError(Lead $lead, string $error): array
    {
        return [
            'ok' => false,
            'error' => $error,
            'available_stages' => $this->pipelineContext->stagesForLead($lead),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getLead(Lead $lead): array
    {
        $lead->load(['contact', 'stage', 'pipeline']);

        $availableStages = $this->pipelineContext->stagesForLead($lead);

        return [
            'id' => $lead->uuid,
            'status' => $lead->status->value,
            'stage_id' => $lead->stage?->uuid,
            'stage_name' => $lead->stage?->name,
            'pipeline' => $lead->pipeline?->name,
            'contact_name' => $lead->contact?->name,
            'contact_phone' => $lead->contact?->phone,
            'notes' => $lead->notes,
            'available_stages' => $availableStages,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function addNote(Lead $lead, array $arguments): array
    {
        $note = trim((string) ($arguments['note'] ?? ''));

        if ($note === '') {
            throw ValidationException::withMessages(['note' => ['Observação vazia.']]);
        }

        $result = $this->noteAppender->append($lead->notes, $note);

        if (! $result['appended']) {
            return [
                'ok' => true,
                'appended' => false,
                'message' => 'Nenhuma informação nova; observação duplicada ignorada.',
            ];
        }

        $lead->forceFill([
            'notes' => $result['notes'],
            'last_interaction_at' => now(),
        ])->save();

        return ['ok' => true, 'appended' => true];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function requestHumanHandoff(Lead $lead, ?Conversation $conversation, array $arguments): array
    {
        if ($conversation === null) {
            return ['ok' => false, 'error' => 'Conversa não disponível para handoff.'];
        }

        $reason = trim((string) ($arguments['reason'] ?? ''));

        if ($reason === '') {
            return ['ok' => false, 'error' => 'Informe reason (motivo do handoff).'];
        }

        $messageToCustomer = trim((string) ($arguments['message_to_customer'] ?? ''));

        $this->handoff->escalate(
            $conversation,
            $reason,
            $messageToCustomer !== '' ? $messageToCustomer : null,
            'ai',
        );

        return ['ok' => true, 'handoff' => true];
    }

    /**
     * @param  array<string, mixed>|null  $result
     * @param  array<string, mixed>  $arguments
     */
    private function audit(
        string $tool,
        array $arguments,
        ?array $result,
        string $status,
        Lead $lead,
        ?Conversation $conversation,
        float $started,
        ?string $error = null,
    ): void {
        DB::transaction(function () use ($tool, $arguments, $result, $status, $lead, $conversation, $started, $error): void {
            AiToolExecution::query()->create([
                'tenant_id' => TenantContext::tenantId(),
                'conversation_id' => $conversation?->getKey(),
                'lead_id' => $lead->getKey(),
                'tool' => $tool,
                'arguments' => $arguments,
                'result' => $result,
                'status' => $status,
                'error' => $error,
                'execution_time_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        });
    }
}
