<?php

namespace App\Modules\Client\Services;

use App\Modules\Client\Models\ClientContract;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientContractPdfService
{
    public function __construct(private readonly ContractBodyRenderer $renderer) {}

    public function download(ClientContract $contract, ?string $filename = null): StreamedResponse
    {
        $pdf = $this->render($contract);

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf;
            },
            $filename ?? 'contrato.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function render(ClientContract $contract): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(true);
        $options->setAllowedRemoteHosts([(string) parse_url((string) config('app.url'), PHP_URL_HOST)]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->htmlDocument($contract), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function htmlDocument(ClientContract $contract): string
    {
        $body = $this->renderer->renderLogo(
            (string) $contract->body,
            $this->logoDataUri($contract),
        );

        $body = $this->renderer->renderCompanySignature($body, $this->companySignatureDataUri($contract));

        $body = $this->renderer->renderSignature($body, $this->signatureDataUri($contract));

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
        <meta charset="utf-8" />
        <style>
          @page { margin: 16mm; }
          * { box-sizing: border-box; }
          body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #111827;
            margin: 0;
          }
          p { margin: 0 0 10px; }
          h1, h2, h3, h4 { margin: 16px 0 8px; line-height: 1.3; }
          ul, ol { margin: 0 0 10px; padding-left: 20px; }
          table { width: 100%; border-collapse: collapse; margin: 10px 0; }
          th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
          blockquote { margin: 10px 0; padding: 8px 12px; border-left: 3px solid #cbd5e1; color: #475569; }
          img { max-width: 100%; height: auto; }
        </style>
        </head>
        <body>{$body}</body>
        </html>
        HTML;
    }

    private function logoDataUri(ClientContract $contract): ?string
    {
        $path = $contract->tenant?->logo_path;

        return $path !== null ? $this->storageDataUri($path) : null;
    }

    private function companySignatureDataUri(ClientContract $contract): ?string
    {
        $path = $contract->tenant?->signature_path;

        return $path !== null ? $this->storageDataUri($path) : null;
    }

    private function signatureDataUri(ClientContract $contract): ?string
    {
        return $contract->signature_path !== null
            ? $this->storageDataUri($contract->signature_path)
            : null;
    }

    private function storageDataUri(string $path): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $disk->get($path));
    }
}
