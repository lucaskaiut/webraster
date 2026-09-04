<?php

namespace App\Modules\Assistant\Contracts;

use App\Modules\Assistant\Support\ToolRegistry;
use App\Modules\User\Models\User;

/**
 * Define o "comportamento" do assistente: o system prompt que orienta o
 * modelo e o catálogo de ferramentas (ToolRegistry) disponibilizado a ele.
 *
 * Cada projeto cria sua própria implementação e aponta para ela na chave
 * "agent" de config/assistant.php — sem precisar alterar o motor de chat.
 */
interface AssistantAgent
{
    public function systemPrompt(): string;

    public function tools(User $user): ToolRegistry;
}
