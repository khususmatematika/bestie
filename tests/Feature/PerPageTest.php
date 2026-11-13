<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\PerPage;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class PerPageTest extends TestCase
{
    public function test_per_page_accepts_only_known_sizes_and_all(): void
    {
        $size = static fn (array $q): int => PerPage::resolve(Request::create('/x', 'GET', $q));

        $this->assertSame(15, $size([]));
        $this->assertSame(50, $size(['per_page' => '50']));
        $this->assertSame(100, $size(['per_page' => '100']));
        $this->assertSame(15, $size(['per_page' => '7']));
        $this->assertSame(15, $size(['per_page' => '-1']));
        $this->assertSame(15, $size(['per_page' => '999999']));
        $this->assertSame(PerPage::ALL_LIMIT, $size(['per_page' => 'all']));
    }
}
