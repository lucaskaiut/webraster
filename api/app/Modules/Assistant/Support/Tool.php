<?php

namespace App\Modules\Assistant\Support;

use Closure;

final class Tool
{
    /**
     * @param  array<string, mixed>  $parameters  JSON Schema do objeto de argumentos.
     * @param  Closure(array<string, mixed>): mixed  $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly Closure $handler,
        public readonly bool $writes = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->parameters as $name => $schema) {
            if (($schema['required'] ?? false) === true) {
                $required[] = $name;
            }

            unset($schema['required']);

            $properties[$name] = $schema;
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => [
                    'type' => 'object',
                    // Garante "properties": {} (objeto vazio) em vez de [].
                    'properties' => $properties === [] ? new \stdClass : $properties,
                    'required' => $required,
                ],
            ],
        ];
    }

    public function run(array $arguments): mixed
    {
        return ($this->handler)($arguments);
    }
}
