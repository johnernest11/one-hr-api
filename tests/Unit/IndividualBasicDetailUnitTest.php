<?php

namespace Tests\Unit;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualAddress;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\ComprehensiveRecords\IndividualContactInfo;
use App\Models\User;
use App\Services\ComprehensiveRecords\IndividualBasicDetailService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndividualBasicDetailUnitTest extends TestCase
{
    use RefreshDatabase;

    private IndividualBasicDetailService $individualBasicDetailService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->individualBasicDetailService = new IndividualBasicDetailService(new IndividualBasicDetail());
        $this->user = $this->produceUsers();

    }

    public function generate_test_data(): array
    {
        // Generate random data
        $testIndividual = IndividualBasicDetail::factory()->make()->toArray();
        $testEmployee = Employee::factory()->make()->toArray();
        $testAddress = IndividualAddress::factory()->make()->toArray();
        $testContactInfo = IndividualContactInfo::factory()->make()->toArray();

        // Combine data and structure it so that it is similar to the request body
        $requestData = [
            'individual' => $testIndividual,
            'employee' => [$testEmployee],
            'individualAddress' => [$testAddress],
            'individualContactInfo' => [$testContactInfo],
        ];

        return $requestData;
    }

    /**
     * Test if an IndividualBasicDetail will be created via the service
     */
    public function test_can_create_individual_data(): void
    {
        $this->individualBasicDetailService->store($this->generate_test_data());
        $this->assertDatabaseCount('individual_basic_details', 1);
    }

    /**
     * Test if an IndividualBasicDetail can be edited via the service
     */
    public function test_can_edit_individual_data(): void
    {
        $individual = $this->individualBasicDetailService->store($this->generate_test_data());
        $this->assertDatabaseCount('individual_basic_details', 1);

        $newInfo = $this->generate_test_data();
        // Add ids
        $newInfo['employee'][0]['id'] = $individual->employee->id;
        $newInfo['individualAddress'][0]['id'] = $individual->individualAddress->id;
        $newInfo['individualContactInfo'][0]['id'] = $individual->individualContactInfo->id;
        $updatedData = $this->individualBasicDetailService->update($individual, $newInfo);
        $this->assertDatabaseHas('individual_basic_details', $newInfo['individual']);
    }

    /**
     * Test if all IndividualBasicDetail can be viewed via the service
     */
    public function test_can_view_all_individual_data(): void
    {
        $this->individualBasicDetailService->store($this->generate_test_data());
        $this->assertDatabaseCount('individual_basic_details', 1);

        $this->actingAs($this->user); // simulate user auth
        $paginatedResultsDefault = $this->individualBasicDetailService->all();
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResultsDefault);

        $limit = 20;
        $paginatedResultsWithLimit = $this->individualBasicDetailService->all($limit);
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResultsWithLimit);
    }

    /**
     * Test if an IndividualBasicDetail can be viewed via the service by it's ID
     */
    public function test_can_view_individual_data_by_id(): void
    {
        $individual = $this->individualBasicDetailService->store($this->generate_test_data());
        $this->assertDatabaseCount('individual_basic_details', 1);

        $searchForThis = IndividualBasicDetail::find($individual->id);
        $paginatedResults = $this->individualBasicDetailService->viewConsolidatedData($searchForThis);
        $this->assertEquals($searchForThis->id, $paginatedResults->id);
    }
}
