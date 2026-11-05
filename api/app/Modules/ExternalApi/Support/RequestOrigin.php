<?php

namespace App\Modules\ExternalApi\Support;

use Illuminate\Support\Facades\Route;

/**
 * Descreve o que disparou a chamada externa: rota HTTP, job ou comando.
 * Serve para descobrir quais fluxos mais consomem cada API.
 */
class RequestOrigin
{
    private ?string $job = null;

    public function startJob(string $name): void
    {
        $this->job = $name;
    }

    public function endJob(): void
    {
        $this->job = null;
    }

    public function describe(): ?string
    {
        if ($this->job !== null) {
            return 'job '.$this->job;
        }

        if (app()->runningInConsole()) {
            $argv = $_SERVER['argv'] ?? [];
            $command = is_array($argv) ? trim(implode(' ', array_slice($argv, 1, 2))) : '';

            return $command !== '' ? 'artisan '.$command : 'console';
        }

        $route = Route::current();

        if ($route !== null) {
            return request()->method().' /'.ltrim($route->uri(), '/');
        }

        return request()->method().' '.request()->getPathInfo();
    }
}
