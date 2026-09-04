<?php

namespace App\Modules\Assistant\Services;

use App\Modules\Assistant\Contracts\AssistantAgent;
use App\Modules\Assistant\Enums\MessageRole;
use App\Modules\Assistant\Http\Resources\MessageResource;
use App\Modules\Assistant\Models\Conversation;
use App\Modules\Assistant\Models\Message;
use App\Modules\Assistant\Support\ToolRegistry;
use App\Modules\User\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class AssistantService
{
    public function __construct(
        private readonly OpenAiClient $client,
    ) {}

    public function stream(Conversation $conversation, User $user, string $content): StreamedResponse
    {
        $config = $this->connectionConfig();
        $agent = $this->resolveAgent();
        $registry = $agent->tools($user);

        $this->recordUserMessage($conversation, $content);

        $history = $this->history($conversation);
        $system = $agent->systemPrompt();

        return response()->stream(function () use ($conversation, $config, $registry, $history, $system): void {
            $this->run($conversation, $config, $registry, $history, $system);
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array{endpoint: string, api_key: string, model: string, temperature: float, max_tokens: ?int}
     */
    private function connectionConfig(): array
    {
        if (! (bool) config('assistant.enabled', true)) {
            throw new RuntimeException('O assistente de IA não está habilitado.');
        }

        $connection = config('assistant.connection', []);

        $endpoint = rtrim((string) ($connection['endpoint'] ?? ''), '/');
        $apiKey = (string) ($connection['api_key'] ?? '');
        $model = (string) ($connection['model'] ?? '');

        if ($endpoint === '') {
            throw new RuntimeException('O endpoint da API de IA não foi configurado.');
        }

        if ($apiKey === '') {
            throw new RuntimeException('A chave da API de IA não foi configurada.');
        }

        if ($model === '') {
            throw new RuntimeException('O modelo de IA não foi configurado.');
        }

        return [
            'endpoint' => $endpoint,
            'api_key' => $apiKey,
            'model' => $model,
            'temperature' => (float) ($connection['temperature'] ?? 0.2),
            'max_tokens' => isset($connection['max_tokens']) && $connection['max_tokens'] !== null
                ? (int) $connection['max_tokens']
                : null,
        ];
    }

    private function resolveAgent(): AssistantAgent
    {
        $class = config('assistant.agent');

        $agent = is_string($class) && class_exists($class) ? app($class) : null;

        if (! $agent instanceof AssistantAgent) {
            throw new RuntimeException('Nenhum agente de IA configurado para este projeto.');
        }

        return $agent;
    }

    /**
     * @param  array{endpoint: string, api_key: string, model: string, temperature: float, max_tokens: ?int}  $config
     * @param  list<array<string, mixed>>  $history
     */
    private function run(Conversation $conversation, array $config, ToolRegistry $registry, array $history, string $system): void
    {
        $emit = function (string $type, array $data = []): void {
            echo 'data: '.json_encode(['type' => $type, ...$data], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";

            if (ob_get_level() > 0) {
                ob_flush();
            }

            flush();
        };

        $messages = [['role' => 'system', 'content' => $system], ...$history];
        $definitions = $registry->definitions();
        $maxIterations = (int) config('assistant.max_tool_iterations', 6);

        try {
            for ($i = 0; $i < $maxIterations; $i++) {
                $result = $this->client->streamChat(
                    $config,
                    $messages,
                    $definitions,
                    fn (string $delta) => $emit('delta', ['content' => $delta]),
                );

                if ($result['tool_calls'] === []) {
                    $message = $this->recordAssistantMessage($conversation, $result['content']);
                    $emit('done', ['message' => MessageResource::make($message)->resolve()]);

                    return;
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $result['content'] !== '' ? $result['content'] : null,
                    'tool_calls' => $this->openAiToolCalls($result['tool_calls']),
                ];

                $this->recordAssistantToolCalls($conversation, $result['tool_calls']);

                $emit('tool', [
                    'calls' => array_map(
                        fn (array $call) => ['name' => $call['name'], 'arguments' => $call['arguments']],
                        $result['tool_calls'],
                    ),
                ]);

                foreach ($result['tool_calls'] as $call) {
                    $execution = $this->executeTool($registry, $call);

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'],
                        'name' => $call['name'],
                        'content' => json_encode($execution, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    ];

                    $this->recordToolResult($conversation, $call, $execution);

                    $emit('tool_result', ['name' => $call['name'], 'ok' => $execution['ok']]);
                }
            }

            $emit('error', ['message' => 'O assistente excedeu o limite de operações. Reformule sua pergunta.']);
        } catch (Throwable $e) {
            $this->recordAssistantMessage($conversation, 'Não consegui concluir a operação: '.$e->getMessage());
            $emit('error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param  array{id: string, name: string, arguments: array<string, mixed>}  $call
     * @return array{ok: bool, result: mixed, error: ?string}
     */
    private function executeTool(ToolRegistry $registry, array $call): array
    {
        try {
            $tool = $registry->get($call['name']);

            return ['ok' => true, 'result' => $tool->run($call['arguments']), 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'result' => null, 'error' => $e->getMessage()];
        }
    }

    private function recordUserMessage(Conversation $conversation, string $content): void
    {
        $first = $conversation->messages()->count() === 0;

        Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'role' => MessageRole::User,
            'content' => $content,
        ]);

        if ($first || $conversation->title === 'Nova conversa') {
            $conversation->title = Str::limit(trim(preg_replace('/\s+/', ' ', $content) ?? 'Nova conversa'), 60, '');
            $conversation->save();
        }
    }

    /**
     * @param  list<array{id: string, name: string, arguments: array<string, mixed>}>  $calls
     */
    private function recordAssistantToolCalls(Conversation $conversation, array $calls): void
    {
        Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'role' => MessageRole::Assistant,
            'content' => '',
            'tool_calls' => $calls,
        ]);
    }

    /**
     * @param  array{id: string, name: string, arguments: array<string, mixed>}  $call
     * @param  array{ok: bool, result: mixed, error: ?string}  $execution
     */
    private function recordToolResult(Conversation $conversation, array $call, array $execution): void
    {
        Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'role' => MessageRole::Tool,
            'content' => json_encode($execution, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'tool_results' => [['id' => $call['id'], 'name' => $call['name'], 'arguments' => $call['arguments']]],
        ]);
    }

    private function recordAssistantMessage(Conversation $conversation, string $content): Message
    {
        return Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'role' => MessageRole::Assistant,
            'content' => $content,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(Conversation $conversation): array
    {
        $limit = (int) config('assistant.history_limit', 40);

        $messages = $conversation->messages()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();

        $history = [];

        foreach ($messages as $message) {
            $entry = match ($message->role) {
                MessageRole::User => ['role' => 'user', 'content' => $message->content],
                MessageRole::Assistant => $this->assistantHistoryEntry($message),
                MessageRole::Tool => $this->toolHistoryEntry($message),
                default => null,
            };

            if ($entry !== null) {
                $history[] = $entry;
            }
        }

        return $history;
    }

    /**
     * @return array<string, mixed>
     */
    private function assistantHistoryEntry(Message $message): array
    {
        $calls = $message->tool_calls ?? [];

        if ($calls === []) {
            return ['role' => 'assistant', 'content' => $message->content];
        }

        return [
            'role' => 'assistant',
            'content' => $message->content !== '' ? $message->content : null,
            'tool_calls' => $this->openAiToolCalls($calls),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toolHistoryEntry(Message $message): ?array
    {
        $meta = ($message->tool_results ?? [])[0] ?? null;

        if ($meta === null) {
            return null;
        }

        return [
            'role' => 'tool',
            'tool_call_id' => $meta['id'],
            'name' => $meta['name'],
            'content' => $message->content,
        ];
    }

    /**
     * @param  list<array{id: string, name: string, arguments: array<string, mixed>}>  $calls
     * @return list<array{id: string, type: string, function: array{name: string, arguments: string}}>
     */
    private function openAiToolCalls(array $calls): array
    {
        return array_map(fn (array $call) => [
            'id' => $call['id'],
            'type' => 'function',
            'function' => [
                'name' => $call['name'],
                'arguments' => json_encode($call['arguments'] ?? [], JSON_UNESCAPED_UNICODE),
            ],
        ], $calls);
    }
}
