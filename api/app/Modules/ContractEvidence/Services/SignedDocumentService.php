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

    /**
     * Gera o PDF final: assinatura no lugar reservado e um selo compacto com
     * QR Code apontando para a página pública de validação. Os detalhes das
     * evidências ficam no manifesto, não no documento.
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

        $body = str_replace($this->signatureSlot($sessionUuid), $image, $presentedHtml);

        return $this->pdf->renderPdf($body.$this->stamp($verificationCode, $signerName, $signedAt));
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

    private function stamp(string $verificationCode, string $signerName, CarbonInterface $signedAt): string
    {
        $url = self::verificationUrl($verificationCode);
        $qr = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::M,
            'scale' => 4,
            'quietzoneSize' => 1,
            'outputBase64' => true,
        ])))->render($url);
        $when = CarbonImmutable::instance($signedAt)->setTimezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i:s');

        return '<table class="signature-stamp"><tr>'
            .'<td class="signature-stamp__qr"><img src="'.$qr.'" alt="QR Code de verificação" /></td>'
            .'<td class="signature-stamp__text">'
            .'<strong>Documento assinado eletronicamente</strong><br />'
            .'Assinado por <strong>'.e($signerName).'</strong> em '.e($when).' (horário de Brasília).<br />'
            .'Código de verificação: <span class="signature-stamp__code">'.e($verificationCode).'</span><br />'
            .'Confira a autenticidade em <span class="signature-stamp__code">'.e($url).'</span> ou leia o QR Code.'
            .'</td></tr></table>';
    }
}
