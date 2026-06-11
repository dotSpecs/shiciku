<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    public function test_name_slug_removes_route_placeholder_braces(): void
    {
        $slug = name2slug('左氵右复{左氵右复}');

        $this->assertStringNotContainsString('{', $slug);
        $this->assertStringNotContainsString('}', $slug);
        $this->assertSame('左氵右复左氵右复', $slug);
    }
}
