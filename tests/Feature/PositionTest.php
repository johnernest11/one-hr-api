<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
    }

    private string $baseUri = self::BASE_API_URI.'/libraries/positions';

    public function test_it_can_fetch_all_positions(): void
    {
        $response = $this->getJson($this->baseUri);
        $response->assertStatus(200);
    }

    public function test_it_can_filter_regions_via_level(): void
    {
        $response = $this->getJson($this->baseUri.'?position-level=1');
        $response = $response->json('data');

        // All levels should be 1
        foreach ($response as $item) {
            $this->assertEquals('1', $item['level']);
        }
    }
}
