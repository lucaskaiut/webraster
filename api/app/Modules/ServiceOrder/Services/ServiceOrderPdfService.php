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
        $order->loadMissing([
            'client',
            'vehicle',
            'equipment',
            'technician',
            'items',
            'attachments',
            'checklists.questions.answer.selectedOptions',
            'checklists.questions.answer.photos',
        ]);

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

        $technicianSignature = $this->localImageDataUri($order->technician?->signature_path);
        $technicianSignatureHtml = sprintf(
            '<div class="signature-slot">%s</div>',
            $technicianSignature
                ? "<img src=\"{$technicianSignature}\" class=\"signature-image\" alt=\"Assinatura do técnico\" />"
                : '',
        );

        $clientSignature = $this->localImageDataUri($order->loan_term_signature_path);
        $clientSignatureHtml = sprintf(
            '<div class="signature-slot">%s</div>',
            $clientSignature
                ? "<img src=\"{$clientSignature}\" class=\"signature-image\" alt=\"Assinatura do cliente\" />"
                : '',
        );
        $loanAcceptanceHtml = $order->loan_term_accepted_at
            ? '<div>Aceite do termo de comodato em '.$this->localDateTime($order->loan_term_accepted_at).'</div>'
            : '';
        $signatureNotes = $loanAcceptanceHtml !== ''
            ? '<div class="signature-notes">'.$loanAcceptanceHtml.'</div>'
            : '';

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

        $checklistsHtml = $order->checklists->isEmpty()
            ? ''
            : '<h2>Checklists</h2>'.$order->checklists->map(function ($checklist) use ($e): string {
                $rows = $checklist->questions->map(function ($question) use ($e): string {
                    $answer = $question->answer;
                    $answerText = '—';

                    if ($answer !== null) {
                        if (filled($answer->text_answer)) {
                            $answerText = $e($answer->text_answer);
                        } elseif ($answer->selectedOptions->isNotEmpty()) {
                            $answerText = $e($answer->selectedOptions->pluck('label')->implode(', '));
                        }
                    }

                    $photosHtml = $answer === null
                        ? ''
                        : $answer->photos->map(function ($photo) use ($e): string {
                            $dataUri = $this->diskImageDataUri($photo->disk, $photo->path);

                            return $dataUri === null
                                ? ''
                                : sprintf(
                                    '<img src="%s" class="checklist-photo" alt="%s" />',
                                    $dataUri,
                                    $e($photo->original_name),
                                );
                        })->implode('');

                    return sprintf(
                        '<tr><td><div>%s</div>%s</td><td>%s</td></tr>',
                        $e($question->prompt),
                        $photosHtml,
                        $answerText,
                    );
                })->implode('');

                $meta = array_filter([
                    $checklist->type?->label(),
                    $checklist->status?->label(),
                    $checklist->completed_at
                        ? 'Concluído em '.$this->localDateTime($checklist->completed_at)
                        : null,
                ]);

                $questionsTable = $rows === ''
                    ? '<p class="muted">Nenhuma pergunta registrada.</p>'
                    : '<table class="checklist-table"><thead><tr><th>Pergunta</th><th>Resposta</th></tr></thead><tbody>'.$rows.'</tbody></table>';

                return sprintf(
                    '<h3 class="checklist-title">%s <span class="muted">%s</span></h3>%s',
                    $e($checklist->name),
                    $e(implode(' · ', $meta)),
                    $questionsTable,
                );
            })->implode('');

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
          h3.checklist-title { font-size: 11px; color: #111827; margin: 12px 0 4px; }
          h3.checklist-title .muted { font-weight: normal; font-size: 10px; }
          .checklist-table { margin-bottom: 6px; }
          .checklist-table th:first-child { width: 68%; }
          .checklist-table td { vertical-align: top; }
          .checklist-photo { height: 50px; width: auto; max-width: 100%; margin-top: 4px; margin-right: 4px; }
          .grid { width: 100%; border-collapse: collapse; }
          .grid td { width: 50%; vertical-align: top; padding: 2px 8px 2px 0; }
          .label { color: #6b7280; font-size: 10px; text-transform: uppercase; }
          table { width: 100%; border-collapse: collapse; }
          th { text-align: left; font-size: 10px; color: #6b7280; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; padding: 4px; }
          tbody td, tfoot td { padding: 4px; border-bottom: 1px solid #f3f4f6; }
          .num { text-align: right; }
          .signatures { width: 100%; margin-top: 48px; }
          .signatures td { width: 50%; text-align: center; padding: 0 16px; vertical-align: top; }
          .signature-slot { height: 62px; }
          .signature-image { max-height: 56px; max-width: 190px; margin: 0 auto 2px; }
          .line { border-top: 1px solid #111827; padding-top: 4px; }
          .signature-notes { margin-top: 8px; font-size: 10px; color: #6b7280; text-align: center; }
          .signature-notes div { margin-top: 2px; }
        </style>
        </head>
        <body>
          <table class="header"><tr>
            <td>{$logoHtml}<div class="muted">{$e($tenant?->name)}</div></td>
            <td style="text-align:right">
              <div class="code">Ordem de serviço {$e($order->code)}</div>
              <div class="muted">Situação: {$e($order->status?->label())}</div>
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
            </tr>
          </table>

          <h2>Serviços</h2>
          {$itemsTable}
          {$description}
          {$notes}
          {$execution}
          {$checklistsHtml}
          {$attachments}

          <table class="signatures"><tr>
            <td>{$technicianSignatureHtml}<div class="line">Técnico responsável</div></td>
            <td>{$clientSignatureHtml}<div class="line">Cliente</div></td>
          </tr></table>
          {$signatureNotes}
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

    private function localImageDataUri(?string $path): ?string
    {
        return $this->diskImageDataUri('local', $path);
    }

    private function diskImageDataUri(?string $diskName, ?string $path): ?string
    {
        if (! $diskName || ! $path) {
            return null;
        }

        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }

    private function money(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
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
