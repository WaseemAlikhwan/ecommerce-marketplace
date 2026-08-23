<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function test_parse_to_minor_is_exact(string $input, int $exponent, int $expected): void
    {
        $this->assertSame($expected, Money::parseToMinor($input, $exponent));
    }

    /**
     * @return array<string, array{string, int, int}>
     */
    public static function validAmounts(): array
    {
        return [
            'syp whole' => ['185000', 0, 185000],
            'usd two decimals' => ['49.99', 2, 4999],
            'usd whole' => ['10', 2, 1000],
            'usd one decimal' => ['10.5', 2, 1050],
            'usd zero padded' => ['0.05', 2, 5],
        ];
    }

    public function test_format_from_minor_round_trips(): void
    {
        $this->assertSame('185000', Money::formatFromMinor(185000, 0));
        $this->assertSame('49.99', Money::formatFromMinor(4999, 2));
        $this->assertSame('10.50', Money::formatFromMinor(1050, 2));
    }

    public function test_excess_decimals_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parseToMinor('10.999', 2);
    }

    public function test_syp_rejects_fraction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parseToMinor('10.5', 0);
    }

    public function test_negative_rejected_with_dedicated_message(): void
    {
        try {
            Money::parseToMinor('-1', 0);
            $this->fail('Expected InvalidArgumentException for negative amount.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(__('Negative amounts are not allowed.'), $exception->getMessage());
        }
    }

    public function test_php_int_max_boundary_is_accepted_for_syp(): void
    {
        $max = (string) PHP_INT_MAX;

        $this->assertSame(PHP_INT_MAX, Money::parseToMinor($max, 0));
    }

    public function test_overflow_rejected_for_syp_without_float(): void
    {
        $overflow = (string) PHP_INT_MAX.'0';

        try {
            Money::parseToMinor($overflow, 0);
            $this->fail('Expected InvalidArgumentException for oversized SYP amount.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(__('The amount is too large.'), $exception->getMessage());
        }
    }

    public function test_overflow_rejected_for_usd_minor_units(): void
    {
        $whole = (string) PHP_INT_MAX;

        try {
            Money::parseToMinor($whole.'.00', 2);
            $this->fail('Expected InvalidArgumentException for oversized USD amount.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(__('The amount is too large.'), $exception->getMessage());
        }
    }
}
