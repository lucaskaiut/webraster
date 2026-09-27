<?php

namespace App\Modules\Chat\Support;

use Illuminate\Validation\ValidationException;

final class EvolutionConfig
{
    public static function baseUrl(): string
    {
        return rtrim((string) config('chat.evolution.base_url', ''), '/');
    }

    public static function apiKey(): string
    {
        return (string) config('chat.evolution.api_key', '');
    }

    public static function isConfigured(): bool
    {
        return self::baseUrl() !== '' && self::apiKey() !== '';
    }

    public static function assertConfigured(): void
    {
        if (self::isConfigured()) {
            return;
        }

        throw ValidationException::withMessages([
            'evolution' => ['Configure EVOLUTION_API_BASE_URL e EVOLUTION_API_KEY no ambiente do servidor.'],
        ]);
    }
}
