<?php

namespace App\Modules\Webhook\Services;

class WebhookEventRegistry
{
    private array $events = [];

    public function register(array $events): void
    {
        foreach ($events as $value => $label) {
            if (is_int($value)) {
                $value = $label;
            }
            $this->events[$value] = $label;
        }
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->events as $value => $label) {
            $result[] = ['value' => $value, 'label' => $label];
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public function eventNames(): array
    {
        return array_keys($this->events);
    }

    public function has(string $event): bool
    {
        return array_key_exists($event, $this->events);
    }
}
