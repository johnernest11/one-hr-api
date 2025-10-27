<?php

namespace Tests\Unit;

use App\Enums\ApprovalType;
use App\Enums\LocatorFormType;
use App\Enums\Period;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\LocatorSlip\LocatorSlip;
use App\Models\User;
use App\Services\DailyTimeRecords\DailyTimeRecordManager;
use App\Services\LocatorSlips\LocatorSlipService;
use ConversionHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Tests\TestCase;

class LocatorSlipUnitTest extends TestCase
{
    use RefreshDatabase;

    private LocatorSlipService $locatorSlipService;

    private User $user;

    private Employee $employee;

    private Employee $employee_2;

    private IndividualBasicDetail $individual;

    private IndividualBasicDetail $individual_2;

    private array $testInputLocator;

    private array $testInputLogs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->locatorSlipService = new LocatorSlipService(new LocatorSlip, $this->app->make(DailyTimeRecordManager::class));
        $this->user = $this->produceUsers();
        $this->individual = IndividualBasicDetail::factory()->create();
        $this->employee = Employee::whereBelongsTo($this->individual)->firstOrFail();

        $this->individual_2 = IndividualBasicDetail::factory()->create();
        $this->employee_2 = Employee::whereBelongsTo($this->individual_2)->firstOrFail();
        $this->testInputLocator = [
            'form_type' => fake()->randomElement(ConversionHelper::enumToArray(LocatorFormType::class)),
        ];
        if ($this->testInputLocator['form_type'] == LocatorFormType::FORM_C->value) {
            $this->testInputLocator['period'] = fake()->randomElement(ConversionHelper::enumToArray(Period::class));
        }
        $this->testInputLogs = [
            'locator_slip_logger' => [
                [
                    'date' => now()->toDateString(),
                    'time_out' => null,
                    'time_in' => null,
                    'destination' => fake()->text(),
                    'purpose' => fake()->text(),
                    'approved_for' => fake()->randomElement(ConversionHelper::enumToArray(ApprovalType::class)),
                    'duration' => 0,
                    'remarks' => fake()->text(),
                ],
            ],
        ];

    }

    /**
     * Test if a Locator Slip will be created via the service
     */
    public function test_can_create_locator_slip(): void
    {
        $this->locatorSlipService->create($this->employee, $this->testInputLocator);
        $this->assertDatabaseCount('locator_slips', 1);
    }

    /**
     * Test if a Locator Slip Logs can be updated via the service
     */
    public function test_can_edit_locator_slip_logs(): void
    {
        $ls = $this->locatorSlipService->create($this->employee, $this->testInputLocator);
        $this->assertDatabaseCount('locator_slips', 1);

        // Should be able to create locator slip logs
        $lsl = $this->locatorSlipService->update($ls, $this->testInputLogs);
        $this->assertDatabaseCount('locator_slip_loggers', 1);

        $newData = [
            'locator_slip_logger' => [
                [
                    'id' => $lsl->locatorSlipLogger->first()->id,
                    'date' => now()->toDateString(),
                    'time_out' => null,
                    'time_in' => null,
                    'destination' => fake()->text(),
                    'purpose' => fake()->text(),
                    'approved_for' => fake()->randomElement(ConversionHelper::enumToArray(ApprovalType::class)),
                    'duration' => 0,
                    'remarks' => fake()->text(),
                ],
            ],
        ];

        // Should be able to update the created locator slip logs
        $updatedLsl = $this->locatorSlipService->update($ls, $newData);
        $updatedLsl = $updatedLsl->locatorSlipLogger->first();
        $this->assertEquals($newData['locator_slip_logger'][0]['purpose'], $updatedLsl->purpose);
    }

    /**
     * Test if an employee's Locator Slips can be viewed via the service
     */
    public function test_can_view_employee_locator(): void
    {
        $ls = $this->locatorSlipService->create($this->employee, $this->testInputLocator);
        $this->assertDatabaseCount('locator_slips', 1);

        $paginatedResults = $this->locatorSlipService->viewEmployeeLocator($this->employee);
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResults);
    }

    /**
     * Test if the employee's grouped Locator Slips can be viewed via the service
     */
    public function test_can_view_grouped_locator_slips(): void
    {
        $this->locatorSlipService->create($this->employee, $this->testInputLocator);
        $this->locatorSlipService->create($this->employee_2, $this->testInputLocator);
        $this->assertDatabaseCount('locator_slips', 2);

        $paginatedResults = $this->locatorSlipService->readGrouped();
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResults);
        $this->assertCount(3, $paginatedResults->items());
    }

    /**
     * Test if an locator slip can be viewed via the service by it's ID
     */
    public function test_can_view_locator_slip_by_id(): void
    {
        $ls = $this->locatorSlipService->create($this->employee, $this->testInputLocator);
        $this->assertDatabaseCount('locator_slips', 1);

        $searchForThis = LocatorSlip::find($ls->id);
        $paginatedResults = $this->locatorSlipService->read($searchForThis);
        $this->assertEquals($searchForThis->id, $paginatedResults->id);
    }

    public function test_can_search_my_locator_slip(): void
    {
        $initialLsData = ['form_type' => LocatorFormType::FORM_C->value];
        $initialLs = $this->locatorSlipService->create($this->employee, $initialLsData);
        $query = $initialLs->locator_slip_no;
        $result = $this->locatorSlipService->search($this->employee, $query);

        // Compare result to the expected types of response from the service and the new number should match with the query
        if ($result instanceof Collection || $result instanceof Paginator || $result instanceof LengthAwarePaginator || $result instanceof CursorPaginator) {
            $locatorSlips = ($result instanceof Collection) ? $result : $result->items();

            foreach ($locatorSlips as $ls) {
                $this->assertStringContainsString($query, $ls['locator_slip_no']);
            }
        }

    }

    public function test_can_search_all_locator_slips(): void
    {
        $initialLsData = ['form_type' => LocatorFormType::FORM_C->value];
        $initialLs = $this->locatorSlipService->create($this->employee, $initialLsData);
        $query = $initialLs->locator_slip_no;
        $result = $this->locatorSlipService->searchAll($query);

        $locatorSlips = ($result instanceof Collection) ? $result : $result->items();

        $foundMatch = false;

        foreach ($locatorSlips as $employee) {
            // Assert the query matches either the employee's name or a locator slip number

            $fullName = strtolower(implode(' ', [
                $employee['individual_basic_detail']['first_name'] ?? '',
                $employee['individual_basic_detail']['middle_name'] ?? '',
                $employee['individual_basic_detail']['last_name'] ?? '',
            ]));

            if (str_contains($fullName, strtolower($query))) {
                $foundMatch = true;
                break;
            }

            foreach ($employee->locatorSlip as $slip) {
                if (str_contains(strtolower($slip['locator_slip_no']), strtolower($query))) {
                    $foundMatch = true;
                    break 2;
                }
            }
        }

        $this->assertTrue($foundMatch, "The search query '{$query}' was not found in the returned collection.");
    }

    public function test_can_check_active_log(): void
    {
        $ls = $this->locatorSlipService->create($this->employee, $this->testInputLocator);
        $this->assertDatabaseCount('locator_slips', 1);

        $newData = [
            'locator_slip_logger' => [
                [
                    'date' => now()->toDateString(),
                    'time_out' => null,
                    'time_in' => null,
                    'destination' => fake()->text(),
                    'purpose' => fake()->text(),
                    'approved_for' => fake()->randomElement(ConversionHelper::enumToArray(ApprovalType::class)),
                    'duration' => 0,
                    'remarks' => fake()->text(),
                ],
            ],
        ];

        // Should be able to create locator slip logs
        $lsl = $this->locatorSlipService->update($ls, $newData);
        $this->assertDatabaseCount('locator_slip_loggers', 1);

        // Should be able to update the created locator slip logs
        $activeLog = $this->locatorSlipService->checkActiveLog($this->employee);
        $this->assertNotNull($activeLog); // Assert that it fetches the newly created log

    }

    /**
     * Test if locator slips can be generated into docx via service
     */
    public function test_can_generate_docx(): void
    {
        $ls = $this->locatorSlipService->create($this->employee, $this->testInputLocator);
        $this->assertDatabaseCount('locator_slips', 1);

        $response = $this->locatorSlipService->generate($this->employee, $ls);

        $this->assertNotEmpty($response['fileContent']);

    }
}
