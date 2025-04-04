<?php

namespace Tests\Unit;

use App\Enums\EmploymentStatus;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\User;
use App\Services\Item\ItemService;
use ConversionHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Tests\TestCase;

class ItemUnitTest extends TestCase
{
    use RefreshDatabase;

    private ItemService $itemService;

    private User $user;

    private array $testInput = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $itemModel = new Item();
        $this->itemService = new ItemService($itemModel);
        $this->user = $this->produceUsers();
        $this->testInput = [
            'number' => fake()->regexify('[A-Z]{3}-[A-Z]{3}-[A-Z]{3}-\d{6}'), // Simulate number format from the provided database
            'date_of_creation' => fake()->date(),
            'status' => 'Unfilled',
            'date_filled_up' => fake()->date(),
            'employment_status' => fake()->randomElement(ConversionHelper::enumToArray(EmploymentStatus::class)),
            'position_id' => 1,
        ];
    }

    /**
     * Test if an Item will be created via the service
     */
    public function test_can_create_item(): void
    {
        $this->itemService->create($this->testInput);
        $this->assertDatabaseCount('items', 1);
    }

    /**
     * Test if an Item can be edited via the service
     */
    public function test_can_edit_item(): void
    {
        $item = $this->itemService->create($this->testInput);
        $this->assertDatabaseCount('items', 1);

        $newInfo = ['status' => 'Filled'];

        $updatedItem = $this->itemService->update($item, $newInfo);
        $this->assertEquals(ItemStatus::FILLED->value, $updatedItem->status->value);
    }

    /**
     * Test if all items can be viewed via the service
     */
    public function test_can_view_all_items(): void
    {
        $this->itemService->create($this->testInput);
        $this->assertDatabaseCount('items', 1);

        $this->actingAs($this->user); // simulate user auth
        $paginatedResults = $this->itemService->all();
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResults);
    }

    /**
     * Test if an item can be viewed via the service by it's ID
     */
    public function test_can_view_item_by_id(): void
    {
        $item = $this->itemService->create($this->testInput);
        $this->assertDatabaseCount('items', 1);

        $searchForThis = Item::find($item->id);
        $paginatedResults = $this->itemService->read($searchForThis);
        $this->assertEquals($searchForThis->id, $paginatedResults->id);
    }

    /**
     * Test if an item can be viewed via the service by it's ID
     */
    public function test_can_search_item(): void
    {
        $initialItem = $this->itemService->create($this->testInput);
        $this->assertDatabaseCount('items', 1);

        // Update Number for easier search
        $newInfo = ['number' => 'FO1-COS-CPIII-000999'];
        $updatedItem = $this->itemService->update($initialItem, $newInfo);

        $q = 'CP III';
        $result = $this->itemService->search($q);

        // Compare result to the expected types of response from the service and the new number should match with the query
        if ($result instanceof Collection || $result instanceof Paginator || $result instanceof LengthAwarePaginator || $result instanceof CursorPaginator) {
            $items = ($result instanceof Collection) ? $result : $result->items();

            foreach ($items as $item) {
                $this->assertStringContainsString($q, $item['number']);
            }
        }
    }
}
