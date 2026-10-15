<?php

namespace App\Modules\ContractEvidence\Services;

use App\Modules\Client\Models\ClientContract;
use App\Modules\Client\Services\ClientContractPdfService;
use App\Modules\Client\Services\ContractBodyRenderer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

/**
 * Snapshot do documento apresentado e geração do PDF final assinado.
 *
 * O snapshot embute logo e assinatura da empresa como data URI, então os
 * bytes apresentados não dependem de arquivos que podem mudar depois. O PDF
 * final é gerado uma única vez a partir desse snapshot e preservado.
 */
class SignedDocumentService
{
    public function __construct(
        private readonly ContractBodyRenderer $renderer,
        private readonly ClientContractPdfService $pdf,
    ) {}

    public function presentedHtml(ClientContract $contract, string $sessionUuid): string
    {
        $body = $this->renderer->renderLogo((string) $contract->body, $this->pdf->logoDataUri($contract));
        $body = $this->renderer->renderCompanySignature($body, $this->pdf->companySignatureDataUri($contract));

        return str_replace(ContractBodyRenderer::SIGNATURE_TOKEN, $this->signatureSlot($sessionUuid), $body);
    }

    /** Altura do selo (pt) e espaço reservado no fim do texto para ele. */
    private const STAMP_HEIGHT = 76;

    private const STAMP_GAP = 14;

    /**
     * Gera o PDF final: a assinatura desenhada substitui {{ASSINATURA_CLIENTE}}
     * e um selo com QR Code (página pública de validação) é desenhado no
     * rodapé da última página. Os detalhes das evidências ficam no manifesto.
     */
    public function seal(
        string $presentedHtml,
        string $sessionUuid,
        string $signatureImage,
        string $verificationCode,
        string $signerName,
        CarbonInterface $signedAt,
    ): string {
        $image = '<img src="'.ClientContractPdfService::dataUri($signatureImage).'" alt="Assinatura do cliente" style="max-width:160px;height:auto;" />';

        $body = str_replace($this->signatureSlot($sessionUuid), $image, $presentedHtml)
            .'<div class="signature-stamp-space" style="height:'.(self::STAMP_HEIGHT + self::STAMP_GAP).'pt;"></div>';

        $qrPath = $this->qrCodeFile(self::verificationUrl($verificationCode));

        try {
            return $this->pdf->renderPdf($body, function (Canvas $canvas, FontMetrics $fonts) use ($qrPath, $verificationCode, $signerName, $signedAt): void {
                $canvas->page_script(function (int $page, int $pages) use ($canvas, $fonts, $qrPath, $verificationCode, $signerName, $signedAt): void {
                    if ($page === $pages) {
                        $this->drawStamp($canvas, $fonts, $qrPath, $verificationCode, $signerName, $signedAt);
                    }
                });
            });
        } finally {
            @unlink($qrPath);
        }
    }

    public static function verificationUrl(string $verificationCode): string
    {
        $base = config('contract_evidence.verification_url')
            ?: rtrim((string) config('app.frontend_url'), '/').'/validar-contrato';

        return rtrim((string) $base, '/').'/'.$verificationCode;
    }

    private function signatureSlot(string $sessionUuid): string
    {
        return '<em data-signature-slot="'.$sessionUuid.'">Assinatura pendente</em>';
    }

    private function qrCodeFile(string $url): string
    {
        $path = tempnam(sys_get_temp_dir(), 'qr').'.png';

        (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::M,
            'scale' => 6,
            'quietzoneSize' => 1,
            'outputBase64' => false,
        ])))->render($url, $path);

        return $path;
    }

    /**
     * Selo no rodapé (dentro da margem de 16mm do documento), no espaço
     * reservado pelo bloco .signature-stamp-space.
     */
    private function drawStamp(
        Canvas $canvas,
        FontMetrics $fonts,
        string $qrPath,
        string $verificationCode,
        string $signerName,
        CarbonInterface $signedAt,
    ): void {
        $margin = 16 * 72 / 25.4;
        $left = $margin;
        $width = $canvas->get_width() - 2 * $margin;
        $top = $canvas->get_height() - $margin - self::STAMP_HEIGHT;
        $qrSize = self::STAMP_HEIGHT - 12;

        $regular = $fonts->getFont('DejaVu Sans');
        $bold = $fonts->getFont('DejaVu Sans', 'bold');
        $gray = [0.2, 0.2, 0.2];
        $size = 7.5;

        $canvas->rectangle($left, $top, $width, self::STAMP_HEIGHT, [0.79, 0.81, 0.84], 0.75);
        $canvas->image($qrPath, $left + 6, $top + 6, $qrSize, $qrSize);

        $x = $left + $qrSize + 16;
        $maxWidth = $left + $width - 8 - $x;
        $when = CarbonImmutable::instance($signedAt)->setTimezone('America/Sao_Paulo')->format('d/m/Y \\à\\s H:i:s');

        $lines = [
            ['Documento assinado eletronicamente', $bold, 8.5],
            ...array_map(
                fn (string $line) => [$line, $regular, $size],
                $this->wrap("Assinado por {$signerName} em {$when} (horário de Brasília).", $regular, $size, $maxWidth, $fonts),
            ),
            ["Código de verificação: {$verificationCode}", $regular, $size],
            ['Confira a autenticidade lendo o QR Code ou acessando:', $regular, $size],
            ...array_map(
                fn (string $line) => [$line, $regular, $size],
                $this->wrap(self::verificationUrl($verificationCode), $regular, $size, $maxWidth, $fonts, ''),
            ),
        ];

        $y = $top + 9;

        foreach ($lines as [$text, $font, $fontSize]) {
            $canvas->text($x, $y, $text, $font, $fontSize, $gray);
            $y += $fontSize + 4;
        }
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $font, float $size, float $maxWidth, FontMetrics $fonts, string $separator = ' '): array
    {
        $tokens = $separator === '' ? mb_str_split($text) : explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($tokens as $token) {
            $candidate = $current === '' ? $token : $current.$separator.$token;

            if ($current !== '' && $fonts->getTextWidth($candidate, $font, $size) > $maxWidth) {
                $lines[] = $current;
                $current = $token;
            } else {
                $current = $candidate;
            }
        }

        return [...$lines, $current];
    }
}
