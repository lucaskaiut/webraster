<?php

namespace Tests\Unit;

use App\Modules\Shared\Support\Document;
use PHPUnit\Framework\TestCase;

class DocumentTest extends TestCase
{
    public function test_valid_cpf_is_accepted(): void
    {
        $this->assertTrue(Document::isValidCpf('52998224725'));
        $this->assertTrue(Document::isValidCpf('529.982.247-25'));
    }

    public function test_invalid_cpf_is_rejected(): void
    {
        $this->assertFalse(Document::isValidCpf('11111111111'));
        $this->assertFalse(Document::isValidCpf('12345678901'));
        $this->assertFalse(Document::isValidCpf('5299822472'));
        $this->assertFalse(Document::isValidCpf(''));
    }

    public function test_valid_cnpj_is_accepted(): void
    {
        $this->assertTrue(Document::isValidCnpj('11222333000181'));
        $this->assertTrue(Document::isValidCnpj('11.222.333/0001-81'));
    }

    public function test_invalid_cnpj_is_rejected(): void
    {
        $this->assertFalse(Document::isValidCnpj('11111111111111'));
        $this->assertFalse(Document::isValidCnpj('12345678000100'));
        $this->assertFalse(Document::isValidCnpj('1122233300018'));
        $this->assertFalse(Document::isValidCnpj(''));
    }

    public function test_fake_documents_are_valid(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->assertTrue(Document::isValidCpf(Document::fakeCpf()));
            $this->assertTrue(Document::isValidCnpj(Document::fakeCnpj()));
        }
    }
}
