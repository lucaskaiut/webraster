<?php

namespace Tests\Unit\ContractEvidence;

use App\Modules\ContractEvidence\Support\CanonicalJson;
use App\Modules\ContractEvidence\Support\EvidenceKeyring;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CanonicalJsonTest extends TestCase
{
    public function test_serialization_is_independent_of_key_order(): void
    {
        $a = ['b' => 1, 'a' => ['z' => true, 'y' => null], 'list' => [3, 1, 2]];
        $b = ['list' => [3, 1, 2], 'a' => ['y' => null, 'z' => true], 'b' => 1];

        $this->assertSame(CanonicalJson::encode($a), CanonicalJson::encode($b));
        $this->assertSame('{"a":{"y":null,"z":true},"b":1,"list":[3,1,2]}', CanonicalJson::encode($a));
    }

    public function test_unicode_and_slashes_are_not_escaped(): void
    {
        $this->assertSame('{"texto":"ção/é"}', CanonicalJson::encode(['texto' => 'ção/é']));
    }

    public function test_floats_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CanonicalJson::encode(['valor' => 1.5]);
    }

    public function test_timestamps_are_utc_with_microseconds(): void
    {
        $date = new DateTimeImmutable('2026-10-14 09:30:00.123456', new DateTimeZone('America/Sao_Paulo'));

        $this->assertSame('2026-10-14T12:30:00.123456Z', CanonicalJson::timestamp($date));
    }

    public function test_keyring_signs_and_verifies_with_explicit_config(): void
    {
        $key = EvidenceKeyring::generate();
        $keyring = new EvidenceKeyring(['private_key' => $key['secret_key'], 'trusted_public_keys' => '']);

        $signed = $keyring->sign('mensagem');
        $public = base64_decode($key['public_key'], true);

        $this->assertSame($key['key_id'], $signed['key_id']);
        $this->assertTrue(EvidenceKeyring::verify('mensagem', $signed['signature'], $public));
        $this->assertFalse(EvidenceKeyring::verify('mensagem alterada', $signed['signature'], $public));
        $this->assertStringContainsString('BEGIN PUBLIC KEY', EvidenceKeyring::publicKeyPem($public));
    }
}
