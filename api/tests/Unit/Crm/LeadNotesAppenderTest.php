<?php

namespace Tests\Unit\Crm;

use App\Modules\Crm\Support\LeadNotesAppender;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LeadNotesAppenderTest extends TestCase
{
    private LeadNotesAppender $appender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appender = new LeadNotesAppender;
    }

    #[Test]
    public function it_appends_first_note(): void
    {
        $result = $this->appender->append(null, 'Cliente pediu demonstração.');

        $this->assertTrue($result['appended']);
        $this->assertSame('Cliente pediu demonstração.', $result['notes']);
    }

    #[Test]
    public function it_skips_exact_duplicate_line(): void
    {
        $existing = "Linha A\nLinha B";
        $result = $this->appender->append($existing, 'Linha B');

        $this->assertFalse($result['appended']);
        $this->assertSame('duplicate', $result['skipped_reason']);
        $this->assertSame($existing, $result['notes']);
    }

    #[Test]
    public function it_strips_pasted_full_notes_prefix(): void
    {
        $existing = "Resumo antigo\nDetalhe 1";
        $incoming = "Resumo antigo\nDetalhe 1\nCliente confirmou horário: tarde.";

        $result = $this->appender->append($existing, $incoming);

        $this->assertTrue($result['appended']);
        $this->assertSame("Resumo antigo\nDetalhe 1\nCliente confirmou horário: tarde.", $result['notes']);
    }

    #[Test]
    public function it_skips_near_duplicate_summaries(): void
    {
        $existing = 'Lead Kaiut - Peugeot 208, uso pessoal, foco em segurança, São José dos Pinhais PR.';
        $incoming = 'Lead Kaiut - Peugeot 208, uso pessoal, foco em segurança, São José dos Pinhais/PR.';

        $result = $this->appender->append($existing, $incoming);

        $this->assertFalse($result['appended']);
    }

    #[Test]
    public function it_appends_only_new_lines_from_multi_line_paste(): void
    {
        $existing = "Nota 1\nNota 2";
        $incoming = "Nota 1\nNota 2\nNota 3";

        $result = $this->appender->append($existing, $incoming);

        $this->assertTrue($result['appended']);
        $this->assertSame("Nota 1\nNota 2\nNota 3", $result['notes']);
    }
}
