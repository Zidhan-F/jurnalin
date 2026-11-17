<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ValueObjects\Npsn;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NpsnTest extends TestCase
{
    public function test_it_accepts_valid_8_digit_npsn(): void
    {
        $npsn = new Npsn(' 20219101 ');
        $this->assertSame('20219101', $npsn->value);
    }

    public function test_it_rejects_non_8_digit_npsn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Npsn('202191');
    }
}
