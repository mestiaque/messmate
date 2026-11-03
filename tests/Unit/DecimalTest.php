<?php

namespace Tests\Unit;

use App\Support\Decimal;
use PHPUnit\Framework\TestCase;

class DecimalTest extends TestCase
{
    public function test_multiplication_rounds_half_up(): void
    {
        $this->assertSame('0.5000', Decimal::mul('1', '0.5'));
        $this->assertSame('0.3333', Decimal::mul('1', '0.333333'));
        $this->assertSame('0.6667', Decimal::mul('2', '0.333333'));
        $this->assertSame('-0.6667', Decimal::mul('-2', '0.333333'));
        $this->assertSame('3.38', Decimal::mul('45', '0.075', 2));
    }

    public function test_sum_avoids_float_drift(): void
    {
        $this->assertSame('0.3000', Decimal::sum(['0.1', '0.2']));
        $this->assertSame('2.5000', Decimal::sum(['0.5', '1', '1']));
    }

    public function test_of_normalises_bad_input(): void
    {
        $this->assertSame('0.0000', Decimal::of(null));
        $this->assertSame('0.0000', Decimal::of('abc'));
        $this->assertSame('1.2500', Decimal::of('1.25'));
        $this->assertTrue(Decimal::isZero('0.0000'));
    }
}
