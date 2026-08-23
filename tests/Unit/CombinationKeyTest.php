<?php

namespace Tests\Unit;

use App\Support\CombinationKey;
use InvalidArgumentException;
use Tests\TestCase;

class CombinationKeyTest extends TestCase
{
    public function test_canonical_key_is_independent_of_request_order(): void
    {
        $forward = CombinationKey::forVariable([19 => 44, 12 => 30]);
        $reverse = CombinationKey::forVariable([12 => 30, 19 => 44]);

        $this->assertSame('a12:v30|a19:v44', $forward);
        $this->assertSame($forward, $reverse);
    }

    public function test_literal_default_is_reserved_for_simple_products(): void
    {
        $this->assertSame('default', CombinationKey::SIMPLE);
        $this->assertNotSame(CombinationKey::SIMPLE, CombinationKey::forVariable([12 => 30]));
    }

    public function test_empty_or_invalid_pairs_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CombinationKey::forVariable([]);
    }
}
