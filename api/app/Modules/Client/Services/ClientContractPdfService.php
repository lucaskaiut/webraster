<?php

namespace App\Modules\Client\Services;

use App\Modules\Client\Models\ClientContract;
use App\Modules\ContractEvidence\Models\ContractSignature;
use App\Modules\ContractEvidence\Support\EvidenceStorage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientContractPdfService
{
    public function __construct(
        private readonly ContractBodyRenderer $renderer,
        private readonly EvidenceStorage $evidence,
    ) {}

    /**
     * Documento assinado é sempre servido a partir dos bytes preservados
     * (nunca regerado). Só contratos pendentes ou legados são renderizados.
     */
    public function download(ClientContract $contract, ?string $filename = null): StreamedResponse
    {
        $stored = $this->storedDocument($contract);

        if ($stored !== null) {
            [$bytes, $name, $mediaType] = $stored;

            return $this->stream($bytes, $name ?: ($filename ?? 'contrato.pdf'), $mediaType);
        }

        $disk = Storage::disk('public');

        if ($contract->document_path !== null && $disk->exists($contract->document_path)) {
            return $disk->download(
                $contract->document_path,
                $contract->document_name ?: ($filename ?? 'contrato.pdf'),
            );
        }

        $body = $this->renderer->renderLogo((string) $contract->body, $this->logoDataUri($contract));
        $body = $this->renderer->renderCompanySignature($body, $this->companySignatureDataUri($contract));
        $body = $this->renderer->renderSignature($body, $this->signatureDataUri($contract));

        return $this->stream($this->renderPdf($body), $filename ?? 'contrato.pdf', 'application/pdf');
    }

    /**
     * @return array{0: string, 1: ?string, 2: string}|null bytes, nome e tipo do documento preservado
     */
    public function storedDocument(ClientContract $contract): ?array
    {
        if ($contract->current_signature_id !== null) {
            $signature = ContractSignature::withoutGlobalScopes()->find($contract->current_signature_id);
            $bytes = $this->evidence->read($signature?->document_path);

            if ($signature !== null && $bytes !== null) {
                $mediaType = $signature->document_media_type ?: $this->mediaTypeOf($bytes);
                $name = $mediaType === 'application/pdf'
                    ? ($contract->document_name ?: 'contrato-assinado.pdf')
                    : $contract->document_name;

                return [$bytes, $name, $mediaType];
            }
        }

        if ($this->evidence->isEvidencePath($contract->document_path)) {
            $bytes = $this->evidence->read($contract->document_path);

            return $bytes !== null ? [$bytes, $contract->document_name, $this->mediaTypeOf($bytes)] : null;
        }

        return null;
    }

    private function mediaTypeOf(string $bytes): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
    }

    public function renderPdf(string $bodyHtml): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(true);
        $options->setAllowedRemoteHosts([(string) parse_url((string) config('app.url'), PHP_URL_HOST)]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->htmlDocument($bodyHtml), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function logoDataUri(ClientContract $contract): ?string
    {
        $path = $contract->tenant?->logo_path;

        return $path !== null ? $this->publicDataUri($path) : null;
    }

    public function companySignatureDataUri(ClientContract $contract): ?string
    {
        $path = $contract->tenant?->signature_path;

        return $path !== null ? $this->publicDataUri($path) : null;
    }

    private function signatureDataUri(ClientContract $contract): ?string
    {
        if ($contract->signature_path === null) {
            return null;
        }

        if ($this->evidence->isEvidencePath($contract->signature_path)) {
            $bytes = $this->evidence->read($contract->signature_path);

            return $bytes !== null ? self::dataUri($bytes) : null;
        }

        return $this->publicDataUri($contract->signature_path);
    }

    private function publicDataUri(string $path): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        return self::dataUri((string) $disk->get($path), $disk->mimeType($path) ?: null);
    }

    public static function dataUri(string $bytes, ?string $mime = null): string
    {
        $mime ??= (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function stream(string $bytes, string $filename, string $contentType): StreamedResponse
    {
        return response()->streamDownload(
            static function () use ($bytes): void {
                echo $bytes;
            },
            $filename,
            ['Content-Type' => $contentType],
        );
    }

    private function htmlDocument(string $body): string
    {
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
          .signature-stamp { width: 100%; margin-top: 24px; border: 1px solid #c9ced6; border-radius: 6px; border-collapse: separate; page-break-inside: avoid; font-size: 9px; color: #333; }
          .signature-stamp td { padding: 6px 8px; vertical-align: middle; }
          .signature-stamp__qr { width: 92px; }
          .signature-stamp__qr img { width: 84px; height: 84px; }
          .signature-stamp__text { line-height: 1.5; }
          .signature-stamp__code { font-family: DejaVu Sans Mono, monospace; word-break: break-all; }
        </style>
        </head>
        <body>{$body}</body>
        </html>
        HTML;
    }
}
