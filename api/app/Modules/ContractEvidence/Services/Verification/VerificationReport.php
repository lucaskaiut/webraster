<?php

namespace App\Modules\ContractEvidence\Services\Verification;

final class VerificationReport
{
    public const VALID = 'valid';

    public const INTEGRITY_FAILURE = 'integrity_failure';

    public const MISSING = 'missing';

    public const INCONCLUSIVE = 'inconclusive';

    /** @var list<array{check: string, status: string, detail: string}> */
    private array $checks = [];

    public function __construct(public readonly ?string $signatureId = null) {}

    public function add(string $check, string $status, string $detail): self
    {
        $this->checks[] = ['check' => $check, 'status' => $status, 'detail' => $detail];

        return $this;
    }

    public function status(): string
    {
        $statuses = array_column($this->checks, 'status');

        foreach ([self::INTEGRITY_FAILURE, self::MISSING, self::INCONCLUSIVE] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return $statuses === [] ? self::INCONCLUSIVE : self::VALID;
    }

    public function statusFor(string $check): ?string
    {
        foreach ($this->checks as $item) {
            if ($item['check'] === $check) {
                return $item['status'];
            }
        }

        return null;
    }

    /**
     * @return list<array{check: string, status: string, detail: string}>
     */
    public function checks(): array
    {
        return $this->checks;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'signature_id' => $this->signatureId,
            'status' => $this->status(),
            'checks' => $this->checks,
        ];
    }
}
