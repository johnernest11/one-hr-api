<?php

namespace Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CiCdComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_are_idempotent(): void
    {
        $this->expectNotToPerformAssertions();

        // We seed the first time
        $this->artisan('db:seed');

        // The second call should not result in an error
        $this->artisan('db:seed');
    }
}
