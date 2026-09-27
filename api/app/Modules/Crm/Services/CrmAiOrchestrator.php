<?php

namespace App\Modules\Crm\Services;

use App\Modules\Assistant\Services\OpenAiClient;
use App\Modules\Chat\Enums\SenderType;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Services\OutboundMessageService;
use App\Modules\Crm\Models\AiConfiguration;
use App\Modules\Crm\Models\AiStageConfiguration;
use App\Modules\Crm\Support\CrmAiPipelineContext;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class CrmAiOrchestrator
{
    public function __construct(
        private readonly OpenAiClient $client,
        private readonly CrmToolDispatcher $tools,
        private readonly OutboundMessageService $outbound,
        private readonly CrmAiPipelineContext $pipelineContext,
        private readonly CrmAiHandoffService $handoff,
    ) {}

    public function respond(Conversation $conversation): void
    {
        $conversation->load(['contact', 'lead.stage', 'lead.pipeline', 'connection.tenant']);

        if (! $conversation->isAiEligible()) {
            return;
        }

        $tenantConfig = AiConfiguration::query()->first();

        if ($tenantConfig !== null && ! $tenantConfig->enabled) {
            return;
        }

        $stageConfig = AiStageConfiguration::query()
            ->where('stage_id', $conversation->lead?->stage_id)
            ->first();

        if ($stageConfig !== null && ! $stageConfig->enabled) {
            return;
        }

        $aiConfig = $this->connectionConfig($tenantConfig, $stageConfig);
        $messages = $this->buildMessages($conversation, $tenantConfig, $stageConfig);
        $toolDefs = $this->tools->toolDefinitions();
        $allowed = $stageConfig?->allowed_actions ?? ['move_lead', 'get_lead', 'add_lead_note', 'request_human_handoff'];

        $iterations = (int) config('crm.ai.max_tool_iterations', 4);
        $lead = $conversation->lead;

        if ($lead === null) {
            return;
        }

        for ($i = 0; $i < $iterations; $i++) {
            $result = $this->client->completeChat($aiConfig, $messages, $toolDefs);

            if ($result['tool_calls'] !== []) {
                $assistantMessage = [
                    'role' => 'assistant',
                    'content' => $result['content'] ?? '',
                    'tool_calls' => array_map(
                        static fn (array $call): array => [
                            'id' => $call['id'],
                            'type' => 'function',
                            'function' => [
                                'name' => $call['name'],
                                'arguments' => json_encode($call['arguments'], JSON_THROW_ON_ERROR),
                            ],
                        ],
                        $result['tool_calls'],
                    ),
                ];

                // DeepSeek thinking + tool_calls exige reasoning_content no replay (mesmo vazio).
                $assistantMessage['reasoning_content'] = $result['reasoning_content'] ?? '';

                $messages[] = $assistantMessage;

                $handoffRequested = false;

                foreach ($result['tool_calls'] as $call) {
                    $lead = $lead->fresh(['stage', 'pipeline', 'contact']);

                    $toolResult = $this->tools->dispatch(
                        $call['name'],
                        $call['arguments'],
                        $lead,
                        $conversation,
                        $allowed,
                    );

                    if (($toolResult['handoff'] ?? false) === true) {
                        $handoffRequested = true;
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'],
                        'content' => json_encode($toolResult, JSON_THROW_ON_ERROR),
                    ];
                }

                if ($handoffRequested) {
                    return;
                }

                continue;
            }

            $text = trim((string) ($result['content'] ?? ''));

            if ($text === '') {
                return;
            }

            $systemUser = User::query()->where('tenant_id', $conversation->tenant_id)->first();

            if ($systemUser === null) {
                throw new RuntimeException('Nenhum usuário disponível para envio da IA.');
            }

            $this->outbound->sendText($conversation, $systemUser, $text, SenderType::AI);

            return;
        }

        Log::warning('crm.ai.max_iterations', ['conversation_id' => $conversation->getKey()]);

        $this->handoff->escalate(
            $conversation,
            'Limite de iterações de ferramentas da IA atingido.',
            null,
            'system',
        );
    }

    /**
     * @return array{endpoint: string, api_key: string, model: string, temperature: float, max_tokens: ?int}
     */
    private function connectionConfig(?AiConfiguration $tenantConfig, ?AiStageConfiguration $stageConfig): array
    {
        $endpoint = rtrim(
            (string) ($tenantConfig?->api_endpoint ?: config('crm.ai.endpoint', '')),
            '/',
        );
        $apiKey = (string) ($tenantConfig?->api_key ?: config('crm.ai.api_key', ''));
        $model = (string) ($stageConfig?->model ?? $tenantConfig?->model ?? config('crm.ai.model', ''));
        $temperature = (float) ($stageConfig?->temperature ?? $tenantConfig?->temperature ?? config('crm.ai.temperature', 0.3));
        $maxTokens = $stageConfig?->max_tokens ?? $tenantConfig?->max_tokens ?? config('crm.ai.max_tokens');

        if ($endpoint === '' || $apiKey === '' || $model === '') {
            throw new RuntimeException('IA do CRM não configurada.');
        }

        return [
            'endpoint' => $endpoint,
            'api_key' => $apiKey,
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens !== null ? (int) $maxTokens : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildMessages(
        Conversation $conversation,
        ?AiConfiguration $tenantConfig,
        ?AiStageConfiguration $stageConfig,
    ): array {
        $system = trim((string) ($tenantConfig?->system_prompt ?? 'Você é um assistente comercial. Seja cordial e objetivo.'));

        if ($stageConfig?->objective) {
            $system .= "\n\nObjetivo da etapa: ".$stageConfig->objective;
        }

        if ($stageConfig?->instructions) {
            $system .= "\n\nInstruções:\n".$stageConfig->instructions;
        }

        $lead = $conversation->lead;
        $contact = $conversation->contact;

        $system .= "\n\nContexto do lead:\n";
        $system .= '- Nome: '.($contact?->name ?? 'desconhecido')."\n";
        $system .= '- Telefone: '.($contact?->phone ?? '')."\n";
        $system .= '- Etapa atual: '.($lead?->stage?->name ?? '—')."\n";
        $system .= '- UUID da etapa atual: '.($lead?->stage?->uuid ?? '—')."\n";
        $system .= '- Funil: '.($lead?->pipeline?->name ?? '')."\n";

        if ($lead !== null) {
            $stages = $this->pipelineContext->stagesForLead($lead);
            $system .= "\n\nEtapas do funil (use stage_id em move_lead):\n";
            $system .= $this->pipelineContext->formatStagesForPrompt($stages)."\n";
            $system .= "\nAo mudar de etapa, chame move_lead (UUID ou nome exato da etapa) na mesma rodada de ferramentas.\n";
            $system .= "add_lead_note NÃO altera o kanban; só move_lead move o card. Não diga que avançou etapa sem executar move_lead.\n";
            $system .= "Em add_lead_note, registre só fatos novos desta mensagem (não copie notes nem reescreva o resumo completo do lead).\n";
        }

        $system .= "\nSe não souber responder com segurança, o cliente pedir atendente/vendedor, ou o assunto exigir humano, ";
        $system .= "use request_human_handoff (reason + message_to_customer cordial). Depois do handoff a IA para nesta conversa.\n";

        if ($stageConfig?->success_criteria) {
            $system .= "\n\nCritério de sucesso desta etapa:\n".$stageConfig->success_criteria;
        }

        $messages = [['role' => 'system', 'content' => $system]];

        $historyLimit = (int) config('chat.ai.history_limit', 30);

        $history = $conversation->messages()
            ->orderByDesc('sent_at')
            ->limit($historyLimit)
            ->get()
            ->reverse();

        foreach ($history as $message) {
            if ($message->text === null || $message->text === '') {
                continue;
            }

            $role = $message->direction->value === 'inbound' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $message->text];
        }

        return $messages;
    }
}
