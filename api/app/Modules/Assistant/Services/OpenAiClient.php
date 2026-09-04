<?php

namespace App\Modules\Assistant\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Cliente desacoplado de provedor, compatível com qualquer endpoint
 * que exponha a interface OpenAI Chat Completions.
 */
final class OpenAiClient
{
    /**
     * @param  array{endpoint: string, api_key: string, model: string, temperature: float, max_tokens: ?int}  $config
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  callable(string): void  $onContent
     * @return array{content: string, tool_calls: list<array{id: string, name: string, arguments: array<string, mixed>}>, finish_reason: ?string}
     */
    public function streamChat(array $config, array $messages, array $tools, callable $onContent): array
    {
        $payload = [
            'model' => $config['model'],
            'messages' => $messages,
            'temperature' => $config['temperature'],
            'stream' => true,
        ];

        if ($config['max_tokens'] !== null) {
            $payload['max_tokens'] = $config['max_tokens'];
        }

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $response = $this->request($config, '/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException($this->describeError($response));
        }

        $content = '';
        $finishReason = null;
        /** @var array<int, array{id: ?string, name: ?string, arguments: string}> $toolCalls */
        $toolCalls = [];

        $stream = $response->toPsrResponse()->getBody();

        try {
            $buffer = '';

            while (! $stream->eof()) {
                $chunk = $stream->read(8192);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $buffer .= $chunk;

                while (($newline = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $newline), "\r");
                    $buffer = substr($buffer, $newline + 1);

                    if ($line === '' || ! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $data = trim(substr($line, 5));

                    if ($data === '[DONE]') {
                        break 2;
                    }

                    $decoded = json_decode($data, true);

                    if (! is_array($decoded)) {
                        continue;
                    }

                    $choice = $decoded['choices'][0] ?? null;

                    if (! is_array($choice)) {
                        continue;
                    }

                    $delta = $choice['delta'] ?? [];
                    $finishReason = $choice['finish_reason'] ?? $finishReason;

                    if (isset($delta['content']) && $delta['content'] !== '' && $delta['content'] !== null) {
                        $content .= $delta['content'];
                        $onContent($delta['content']);
                    }

                    foreach ($delta['tool_calls'] ?? [] as $call) {
                        $index = (int) ($call['index'] ?? 0);

                        if (! isset($toolCalls[$index])) {
                            $toolCalls[$index] = ['id' => null, 'name' => null, 'arguments' => ''];
                        }

                        if (isset($call['id'])) {
                            $toolCalls[$index]['id'] = $call['id'];
                        }

                        $function = $call['function'] ?? [];

                        if (isset($function['name'])) {
                            $toolCalls[$index]['name'] = $function['name'];
                        }

                        if (isset($function['arguments'])) {
                            $toolCalls[$index]['arguments'] .= $function['arguments'];
                        }
                    }
                }
            }
        } finally {
            $stream->close();
        }

        $normalized = [];

        foreach ($toolCalls as $call) {
            if ($call['name'] === null) {
                continue;
            }

            $normalized[] = [
                'id' => $call['id'] ?? ('call_'.bin2hex(random_bytes(4))),
                'name' => $call['name'],
                'arguments' => $this->decodeArguments($call['arguments']),
            ];
        }

        return [
            'content' => $content,
            'tool_calls' => $normalized,
            'finish_reason' => $finishReason,
        ];
    }

    /**
     * @param  array{endpoint: string, api_key: string, model: string}  $config
     * @return array{ok: bool, status: string, message: string}
     */
    public function testConnection(array $config): array
    {
        $payload = [
            'model' => $config['model'],
            'messages' => [['role' => 'user', 'content' => 'ping']],
            'max_tokens' => 1,
        ];

        try {
            $response = $this->request($config, '/chat/completions', $payload, withStream: false);
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 'endpoint', 'message' => $e->getMessage()];
        }

        if ($response->successful()) {
            return ['ok' => true, 'status' => 'valid', 'message' => 'Conexão válida.'];
        }

        $detail = $this->extractErrorDetail($response);

        $message = match ($response->status()) {
            401, 403 => 'Erro de autenticação. Verifique a chave da API.',
            404 => 'Modelo não encontrado no endpoint informado.',
            429 => 'Limite de requisições atingido. Tente novamente em instantes.',
            default => 'Endpoint indisponível ou configurado incorretamente.',
        };

        return [
            'ok' => false,
            'status' => match ($response->status()) {
                401, 403 => 'auth',
                404 => 'model',
                default => 'endpoint',
            },
            'message' => $detail !== null ? $message.' '.$detail : $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(array $config, string $path, array $payload, bool $withStream = true): Response
    {
        try {
            $request = Http::withToken($config['api_key'])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => $withStream ? 'text/event-stream' : 'application/json',
                ]);

            $options = ['json' => $payload, 'connect_timeout' => 30];

            if ($withStream) {
                $options['stream'] = true;
                $options['timeout'] = 300;
            } else {
                $options['timeout'] = 60;
            }

            return $request->send('POST', $config['endpoint'].$path, $options);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Não foi possível conectar ao endpoint de IA configurado. Verifique a URL e a disponibilidade do serviço.');
        } catch (Throwable $e) {
            throw new RuntimeException('Falha ao comunicar com o provedor de IA: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeArguments(string $arguments): array
    {
        if (trim($arguments) === '') {
            return [];
        }

        $decoded = json_decode($arguments, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function describeError(Response $response): string
    {
        $status = $response->status();
        $detail = $this->extractErrorDetail($response);

        $base = match ($status) {
            400 => 'Requisição inválida para o provedor de IA.',
            401, 403 => 'Erro de autenticação com o provedor de IA. Verifique a chave da API.',
            404 => 'Modelo não encontrado no endpoint configurado.',
            429 => 'Limite de requisições do provedor atingido. Tente novamente em instantes.',
            default => 'O provedor de IA retornou um erro (HTTP '.$status.').',
        };

        return $detail !== null ? $base.' '.$detail : $base;
    }

    private function extractErrorDetail(Response $response): ?string
    {
        try {
            $data = $response->json();

            if (! is_array($data)) {
                return null;
            }

            $message = data_get($data, 'error.message')
                ?? data_get($data, 'message')
                ?? data_get($data, 'error');

            if (is_string($message) && trim($message) !== '') {
                return $message;
            }
        } catch (Throwable) {
            // corpo não é JSON legível
        }

        return null;
    }
}
