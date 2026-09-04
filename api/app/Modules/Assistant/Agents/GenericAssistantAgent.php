<?php

namespace App\Modules\Assistant\Agents;

use App\Modules\Assistant\Contracts\AssistantAgent;
use App\Modules\Assistant\Support\ToolRegistry;
use App\Modules\User\Models\User;

/**
 * Agente padrão do template — sem vínculo com nenhum domínio.
 *
 * Para criar um assistente específico de projeto:
 * 1. Crie uma classe que implemente AssistantAgent.
 * 2. Em tools(), registre os Tool do domínio (ver Tool::__construct).
 * 3. Aponte ASSISTANT_AGENT (ou a chave "agent" de config/assistant.php)
 *    para essa classe.
 */
final class GenericAssistantAgent implements AssistantAgent
{
    public function systemPrompt(): string
    {
        return <<<'TXT'
Você é o assistente virtual do sistema.

Regras fundamentais:
- Responda SEMPRE em português do Brasil.
- Você NÃO tem acesso direto ao banco de dados: toda informação deve ser obtida pelas ferramentas disponíveis.
- NUNCA invente valores nem assuma dados que não foram retornados por uma ferramenta.
- NUNCA execute operações destrutivas sem confirmação explícita do usuário.
- Se uma ferramenta falhar ou não estiver disponível, explique o problema com clareza.
- Ao listar vários itens, prefira tabelas Markdown.
TXT;
    }

    public function tools(User $user): ToolRegistry
    {
        return new ToolRegistry;
    }
}
