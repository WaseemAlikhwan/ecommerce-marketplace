<?php

namespace Tests\Unit;

use App\Support\CanonicalSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CanonicalSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_unique_slug_suffixes(): void
    {
        DB::table('categories')->insert([
            'slug' => 'home',
            'position' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('home-1', CanonicalSlug::unique('categories', 'Home', 'category'));
        $this->assertSame('fashion', CanonicalSlug::unique('categories', 'Fashion', 'category'));
    }
}
