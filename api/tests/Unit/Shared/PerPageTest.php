<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\PerPage;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class PerPageTest extends TestCase
{
    public function test_reads_number_or_all_from_request(): void
    {
        $this->assertSame(PerPage::ALL, PerPage::fromRequest(Request::create('/?per_page=all')));
        $this->assertSame(50, PerPage::fromRequest(Request::create('/?per_page=50')));
        $this->assertSame(20, PerPage::fromRequest(Request::create('/'), 20));
    }

    public function test_size_clamps_numbers_and_caps_all(): void
    {
        $this->assertSame(1, PerPage::size(0));
        $this->assertSame(100, PerPage::size(500));
        $this->assertSame(50, PerPage::size(50));
        $this->assertSame(PerPage::ALL_LIMIT, PerPage::size(PerPage::ALL));
    }
}
