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
        $this->itemService = new ItemService(new Item);
        $this->user = $this->produceUsers();
        $this->testInput = [
            // Core Identity & Details
            'number' => fake()->regexify('[A-Z]{3}-[A-Z]{3}-[A-Z]{3}-\d{6}'),
            'date_of_creation' => fake()->date(),
            'item_classification' => fake()->randomElement(['Key Positions', 'Technical', 'Support to Technical', 'Administrative']),

            // Organization Data (Foreign Keys)
            'division_id' => 1,
            'section_or_unit_id' => 1,
            'program_id' => 1,
            'office_id' => 1,
            'psipop_id' => 1, // References the divisions table id

            // Compensation & Employment Details
            'employment_status' => fake()->randomElement(ConversionHelper::enumToArray(EmploymentStatus::class)),
            'salary_grade_id' => 1,
            'fund_source_id' => 12,
            'position_id' => 1,

            // Designation Details
            'designation' => fake()->word().' Officer',
            'date_of_designation' => fake()->date(),
            'special_order_number' => 'SO-'.fake()->year().'-0042',

            // Position History and Vacancy Tracking
            'status' => 'Unfilled',
            'mode_of_accession' => null,
            'date_filled_up' => null,
            'history_of_position' => fake()->sentence(),
            'former_incumbent' => fake()->name(),
            'mode_of_separation' => null,
            'date_of_vacant' => fake()->date(),
            'remarks_of_vacancy' => fake()->sentence(),
            'status_of_vacant_position' => 'For Advertisement',
            'remarks' => fake()->paragraph(),
        ];
    }

    /**
     * Test if an Item will be created via the service
     */
    public function test_can_create_item(): void
    {
        $initialCount = Item::count();
        $this->itemService->create($this->testInput);
        $this->assertDatabaseCount('items', $initialCount + 1);
    }

    /**
     * Test if an Item can be edited via the service
     */
    public function test_can_edit_item(): void
    {
        $initialCount = Item::count();
        $item = $this->itemService->create($this->testInput);
        $this->assertDatabaseCount('items', $initialCount + 1);

        $newInfo = ['status' => 'Filled'];

        $updatedItem = $this->itemService->update($item, $newInfo);
        $this->assertEquals(ItemStatus::FILLED->value, $updatedItem->status->value);
    }

    /**
     * Test if all items can be viewed via the service
     */
    public function test_can_view_all_items(): void
    {
        $initialCount = Item::count();
        $this->itemService->create($this->testInput);
        $this->assertDatabaseCount('items', $initialCount + 1);

        $this->actingAs($this->user); // simulate user auth
        $paginatedResults = $this->itemService->all();
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResults);
    }

    /**
     * Test if an item can be viewed via the service by it's ID
     */
    public function test_can_view_item_by_id(): void
    {
        $initialCount = Item::count();
        $item = $this->itemService->create($this->testInput);
        $this->assertDatabaseCount('items', $initialCount + 1);

        $searchForThis = Item::find($item->id);
        $paginatedResults = $this->itemService->read($searchForThis);
        $this->assertEquals($searchForThis->id, $paginatedResults->id);
    }

    /**
     * Test if an item can be viewed via the service by it's ID
     */
    public function test_can_search_item(): void
    {
        $initialCount = Item::count();
        $initialItem = $this->itemService->create($this->testInput);
        $this->assertDatabaseCount('items', $initialCount + 1);

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
