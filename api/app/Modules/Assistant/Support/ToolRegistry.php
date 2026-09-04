<?php

namespace App\Modules\Assistant\Support;

use InvalidArgumentException;

final class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function register(Tool $tool): self
    {
        $this->tools[$tool->name] = $tool;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): Tool
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException("Ferramenta desconhecida: {$name}.");
        }

        return $this->tools[$name];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return array_values(array_map(
            fn (Tool $tool) => $tool->definition(),
            $this->tools,
        ));
    }

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }
}
