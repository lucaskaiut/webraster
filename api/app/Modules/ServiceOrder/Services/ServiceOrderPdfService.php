<?php

namespace App\Modules\ServiceOrder\Services;

use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Models\ServiceOrderAttachment;
use App\Modules\ServiceOrder\Models\ServiceOrderItem;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceOrderPdfService
{
    public function download(ServiceOrder $order): StreamedResponse
    {
        $pdf = $this->render($order);

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf;
            },
            sprintf('%s.pdf', $order->code),
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function render(ServiceOrder $order): string
    {
        $order->loadMissing(['client', 'vehicle', 'equipment', 'technician', 'items', 'attachments']);

        $dompdf = new Dompdf(new Options);
        $dompdf->loadHtml($this->html($order), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function html(ServiceOrder $order): string
    {
        $tenant = Tenant::query()->find($order->tenant_id);
        $client = $order->client;
        $e = fn (mixed $value): string => e((string) ($value ?? ''));
        $dash = fn (mixed $value): string => filled($value) ? e((string) $value) : '—';

        $logo = $this->logoDataUri($tenant);
        $logoHtml = $logo ? "<img src=\"{$logo}\" style=\"max-height:48px;max-width:180px\" />" : '';

        $itemsRows = $order->items->map(fn (ServiceOrderItem $item) => sprintf(
            '<tr><td>%s</td><td class="num">%d</td><td class="num">%s</td><td class="num">%s</td></tr>',
            $e($item->name),
            $item->quantity,
            $this->money($item->unit_amount_cents),
            $this->money($item->totalCents()),
        ))->implode('');

        $itemsTable = $order->items->isEmpty()
            ? '<p class="muted">Nenhum serviço lançado.</p>'
            : sprintf(
                '<table><thead><tr><th>Serviço</th><th class="num">Qtd.</th><th class="num">Valor unit.</th><th class="num">Total</th></tr></thead><tbody>%s</tbody><tfoot><tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong>%s</strong></td></tr></tfoot></table>',
                $itemsRows,
                $this->money($order->itemsTotalCents()),
            );

        $attachments = $order->attachments->isEmpty()
            ? ''
            : '<h2>Anexos</h2><ul>'.$order->attachments
                ->map(fn (ServiceOrderAttachment $attachment) => '<li>'.$e($attachment->original_name).'</li>')
                ->implode('').'</ul>';

        $vehicle = $order->vehicle
            ? trim(implode(' · ', array_filter([$order->vehicle->plate, $order->vehicle->brand, $order->vehicle->model])))
            : null;

        $schedule = $order->scheduled_start_at
            ? sprintf(
                '%s%s',
                $this->localDateTime($order->scheduled_start_at),
                $order->scheduled_end_at ? ' até '.$this->localTime($order->scheduled_end_at) : '',
            )
            : null;

        $travelMinutes = $order->estimated_travel_minutes !== null
            ? (int) $order->estimated_travel_minutes + (int) ($order->estimated_return_travel_minutes ?? $order->estimated_travel_minutes)
            : null;

        $travelDistance = $order->travel_distance_meters !== null
            ? (int) $order->travel_distance_meters + (int) ($order->return_travel_distance_meters ?? $order->travel_distance_meters)
            : null;

        $estimates = array_filter([
            $order->estimated_duration_minutes ? 'Execução: '.$this->minutes($order->estimated_duration_minutes) : null,
            $travelMinutes !== null
                ? sprintf(
                    'Deslocamento total: %s%s',
                    $this->minutes($travelMinutes),
                    $travelDistance ? sprintf(' (%s km)', number_format($travelDistance / 1000, 1, ',', '.')) : '',
                )
                : null,
        ]);

        $description = filled($order->description) ? '<h2>Descrição</h2><p>'.nl2br($e($order->description)).'</p>' : '';
        $notes = filled($order->notes) ? '<h2>Observações</h2><p>'.nl2br($e($order->notes)).'</p>' : '';
        $execution = filled($order->execution_notes) ? '<h2>Execução</h2><p>'.nl2br($e($order->execution_notes)).'</p>' : '';

        $clientDocument = $client?->document ? 'Documento: '.$e($client->document) : '';
        $clientPhone = $client?->phone ? 'Telefone: '.$e($client->phone) : '';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
        <meta charset="utf-8" />
        <style>
          @page { margin: 14mm; }
          body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #111827; line-height: 1.45; }
          .header { width: 100%; margin-bottom: 14px; }
          .header td { vertical-align: middle; }
          .code { font-size: 18px; font-weight: bold; }
          .muted { color: #6b7280; }
          h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #374151; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #e5e7eb; }
          .grid { width: 100%; border-collapse: collapse; }
          .grid td { width: 50%; vertical-align: top; padding: 2px 8px 2px 0; }
          .label { color: #6b7280; font-size: 10px; text-transform: uppercase; }
          table { width: 100%; border-collapse: collapse; }
          th { text-align: left; font-size: 10px; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; padding: 4px; }
          tbody td, tfoot td { padding: 4px; border-bottom: 1px solid #f3f4f6; }
          .num { text-align: right; }
          .signatures { width: 100%; margin-top: 48px; }
          .signatures td { width: 50%; text-align: center; padding: 0 16px; }
          .line { border-top: 1px solid #111827; padding-top: 4px; }
        </style>
        </head>
        <body>
          <table class="header"><tr>
            <td>{$logoHtml}<div class="muted">{$e($tenant?->name)}</div></td>
            <td style="text-align:right">
              <div class="code">Ordem de serviço {$e($order->code)}</div>
              <div class="muted">Situação: {$e($order->status?->label())} · Prioridade: {$e($order->priority?->label())}</div>
            </td>
          </tr></table>

          <h2>Cliente e atendimento</h2>
          <table class="grid">
            <tr>
              <td><div class="label">Cliente</div>{$dash($client?->name)}<br /><span class="muted">{$clientDocument} {$clientPhone}</span></td>
              <td><div class="label">Endereço do atendimento</div>{$dash($order->address)}</td>
            </tr>
            <tr>
              <td><div class="label">Data e horário</div>{$dash($schedule)}</td>
              <td><div class="label">Técnico</div>{$dash($order->technician?->name)}</td>
            </tr>
            <tr>
              <td><div class="label">Veículo</div>{$dash($vehicle)}</td>
              <td><div class="label">Estimativas</div>{$dash(implode(' · ', $estimates))}</td>
            </tr>
          </table>

          <h2>Serviços</h2>
          {$itemsTable}
          {$description}
          {$notes}
          {$execution}
          {$attachments}

          <table class="signatures"><tr>
            <td><div class="line">Técnico responsável</div></td>
            <td><div class="line">Cliente</div></td>
          </tr></table>
        </body>
        </html>
        HTML;
    }

    private function logoDataUri(?Tenant $tenant): ?string
    {
        $path = $tenant?->logo_path;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('public')->get($path));
    }

    private function money(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    private function minutes(int $minutes): string
    {
        return $minutes >= 60
            ? sprintf('%dh%s', intdiv($minutes, 60), $minutes % 60 ? sprintf('%02d', $minutes % 60) : '')
            : $minutes.' min';
    }

    private function localDateTime(CarbonInterface $date): string
    {
        return $date->copy()->setTimezone(config('scheduling.timezone'))->format('d/m/Y H:i');
    }

    private function localTime(CarbonInterface $date): string
    {
        return $date->copy()->setTimezone(config('scheduling.timezone'))->format('H:i');
    }
}
