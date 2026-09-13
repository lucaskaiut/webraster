<?php

namespace App\Modules\Client\Services;

use App\Modules\Client\Models\Client;
use App\Modules\Client\Models\ClientOrder;
use App\Modules\Client\Models\ClientOrderItem;
use App\Modules\Contract\Models\Contract;

class ContractBodyRenderer
{
    public function render(Contract $contract, Client $client, ?ClientOrder $order): string
    {
        $values = $this->substitutionValues($client, $order);

        return str_replace(array_keys($values), array_values($values), $contract->body);
    }

    /**
     * @return array<string, string>
     */
    private function substitutionValues(Client $client, ?ClientOrder $order): array
    {
        return [
            '{{NOME_CLIENTE}}' => $this->escape($client->name),
            '{{DOCUMENTO_CLIENTE}}' => $this->formatDocument($client->document),
            '{{EMAIL_CLIENTE}}' => $client->email !== null ? $this->escape($client->email) : '—',
            '{{TELEFONE_CLIENTE}}' => $this->formatPhone($client->phone),
            '{{ENDERECO_CLIENTE}}' => $this->formatAddress($client),
            '{{TABELA_PEDIDO}}' => $this->buildOrderTable($order),
        ];
    }

    private function buildOrderTable(?ClientOrder $order): string
    {
        if ($order === null || ! $order->relationLoaded('items')) {
            return '<p><em>Nenhum item no pedido.</em></p>';
        }

        $items = $order->items;

        if ($items->isEmpty()) {
            return '<p><em>Nenhum item no pedido.</em></p>';
        }

        $rows = $items
            ->map(function (ClientOrderItem $item): string {
                $plates = $item->relationLoaded('vehicles')
                    ? $item->vehicles->pluck('plate')->filter()->join(', ')
                    : '';

                $plates = $plates !== '' ? $plates : '—';

                return '<tr>'
                    .'<td style="border:1px solid #ccc;padding:8px;">'.$this->escape((string) $item->service_name).'</td>'
                    .'<td style="border:1px solid #ccc;padding:8px;text-align:center;">'.(int) $item->quantity.'</td>'
                    .'<td style="border:1px solid #ccc;padding:8px;">'.$this->escape($plates).'</td>'
                    .'<td style="border:1px solid #ccc;padding:8px;text-align:right;">'.$this->escape($this->formatCurrency($item->unit_amount_cents / 100)).'</td>'
                    .'<td style="border:1px solid #ccc;padding:8px;text-align:right;">'.$this->escape($this->formatCurrency($item->line_total_cents / 100)).'</td>'
                    .'</tr>';
            })
            ->join('');

        $totalCents = (int) $items->sum('line_total_cents');

        return '<table style="width:100%;border-collapse:collapse;margin:1rem 0;">'
            .'<thead>'
            .'<tr>'
            .'<th style="border:1px solid #ccc;padding:8px;text-align:left;">Serviço</th>'
            .'<th style="border:1px solid #ccc;padding:8px;text-align:center;">Qtd.</th>'
            .'<th style="border:1px solid #ccc;padding:8px;text-align:left;">Veículos</th>'
            .'<th style="border:1px solid #ccc;padding:8px;text-align:right;">Valor unit.</th>'
            .'<th style="border:1px solid #ccc;padding:8px;text-align:right;">Total</th>'
            .'</tr>'
            .'</thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'<tfoot>'
            .'<tr>'
            .'<td colspan="4" style="border:1px solid #ccc;padding:8px;text-align:right;font-weight:600;">Total do contrato</td>'
            .'<td style="border:1px solid #ccc;padding:8px;text-align:right;font-weight:600;">'.$this->escape($this->formatCurrency($totalCents / 100)).'</td>'
            .'</tr>'
            .'</tfoot>'
            .'</table>';
    }

    private function formatCurrency(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    private function formatDocument(?string $document): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $document);

        if ($digits === '') {
            return '—';
        }

        if (strlen($digits) === 11) {
            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
        }

        if (strlen($digits) === 14) {
            return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3).'/'.substr($digits, 8, 4).'-'.substr($digits, 12, 2);
        }

        return (string) $document;
    }

    private function formatPhone(?string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return '—';
        }

        $length = strlen($digits);

        if ($length <= 2) {
            return '('.$digits;
        }

        if ($length <= 6) {
            return '('.substr($digits, 0, 2).') '.substr($digits, 2);
        }

        if ($length <= 10) {
            return '('.substr($digits, 0, 2).') '.substr($digits, 2, 4).'-'.substr($digits, 6);
        }

        return '('.substr($digits, 0, 2).') '.substr($digits, 2, 5).'-'.substr($digits, 7);
    }

    private function formatAddress(Client $client): string
    {
        $line1 = trim(implode(', ', array_filter([$client->street, $client->number], static fn ($value) => $value !== null && $value !== '')));

        $withComplement = trim(implode(' — ', array_filter([$line1, $client->complement], static fn ($value) => $value !== null && $value !== '')));

        $cityState = trim(implode('/', array_filter([$client->city, $client->state], static fn ($value) => $value !== null && $value !== '')));

        $line2 = trim(implode(' — ', array_filter([$client->neighborhood, $cityState], static fn ($value) => $value !== null && $value !== '')));

        $zip = $client->zip !== null ? 'CEP '.$this->formatZip($client->zip) : '';

        return trim(implode(', ', array_filter([$withComplement, $line2, $zip], static fn ($value) => $value !== '')));
    }

    private function formatZip(string $zip): string
    {
        $digits = (string) preg_replace('/\D+/', '', $zip);

        if (strlen($digits) === 8) {
            return substr($digits, 0, 5).'-'.substr($digits, 5, 3);
        }

        return $digits;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
