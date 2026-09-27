<?php

namespace App\Modules\Chat\DTOs;

final readonly class EvolutionConnectResult
{
    public function __construct(
        public string $state,
        public ?string $qrcodeBase64 = null,
        public ?string $pairingCode = null,
        public ?int $count = null,
    ) {}
}
