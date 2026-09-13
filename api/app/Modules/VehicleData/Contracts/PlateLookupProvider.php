<?php

namespace App\Modules\VehicleData\Contracts;

use App\Modules\VehicleData\DTOs\VehicleDataDTO;
use App\Modules\VehicleData\Exceptions\PlateLookupException;

/**
 * Porta de saída para provedores de consulta de dados veiculares por placa.
 *
 * O restante do sistema depende apenas deste contrato: para trocar de
 * provedor basta criar uma nova implementação (ex.: App\Modules\VehicleData\
 * Gateways\{StudlyCase}Provider) e adicioná-la em config('vehicledata.providers').
 *
 * Convenção de nomenclatura (igual ao Finance):
 *   chave config (camelCase) → Classe Studly + Provider
 *   apiPlacas → ApiPlacasProvider
 */
interface PlateLookupProvider
{
    public function key(): string;

    public function label(): string;

    /**
     * Indica se o provedor está configurado e ativo para o tenant atual.
     */
    public function isReady(): bool;

    /**
     * Campos de credencial para a UI (o core não conhece chaves do provedor).
     *
     * @return list<array{
     *     name: string,
     *     label: string,
     *     type: string,
     *     required?: bool,
     *     secret?: bool,
     *     hint?: string,
     *     options?: list<array{value: string, label: string}>
     * }>
     */
    public function credentialSchema(): array;

    /**
     * Valores públicos (sem segredos) das credenciais atuais.
     *
     * @return array<string, mixed>
     */
    public function publicCredentials(): array;

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function saveConfig(array $credentials, bool $isActive): void;

    /**
     * Consulta os dados de um veículo a partir da placa (sem máscara).
     *
     * @throws PlateLookupException
     */
    public function lookup(string $plate): VehicleDataDTO;
}
