<?php

namespace Tests\Unit;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualAddress;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\ComprehensiveRecords\IndividualContactInfo;
use App\Models\ComprehensiveRecords\IndividualFamily;
use App\Models\User;
use App\Services\ComprehensiveRecords\IndividualBasicDetailService;
use DB;
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

    /**
     * This function is for truncating all tables in the test database.
     *
     * The problem that this aims to solve:
     * During the test for fulltext search, we have to commit the changes to the database for the fulltext to work properly.
     * After all of the assertions, we have to cleanup the database.
     *
     * We need to truncate every record that was commited to ensure that the succeeding tests will proceed as expected.
     */
    public function truncate_test_db(): void
    {
        DB::statement('SET foreign_key_checks=0'); // Temporarily remove foreign key constraint to truncate tables without running into errors.
        $databaseName = DB::getDatabaseName();
        $tables = DB::select("SELECT * FROM information_schema.tables WHERE table_schema = '$databaseName'");
        foreach ($tables as $table) {
            $name = $table->TABLE_NAME;
            DB::table($name)->truncate();
        }
        DB::statement('SET foreign_key_checks=1'); // Reenable foreign key constraint.
    }

    /**
     * Test if an IndividualBasicDetail can be searched via its name.
     */
    public function test_can_search_individual_data_by_name(): void
    {
        $testData = $this->generate_test_data();
        $testData['individual']['first_name'] = 'TestFirstName';

        $individual = $this->individualBasicDetailService->store($testData);
        $this->assertDatabaseCount('individual_basic_details', 1);

        // Problem: Fulltext does not work in tests: https://dev.mysql.com/doc/refman/en/innodb-fulltext-index.html#innodb-fulltext-index-transaction
        // To resolve this, we need to commit the transaction first and clean the database later.
        // Reference for the solution:
        // https://laracasts.com/discuss/channels/testing/issue-with-data-persistenceeloquent-query-when-running-tests?page=1&replyId=926176
        DB::commit(); // Commit the changes so that the fulltext search will work.

        $q = 'TestFirstName';
        $searchResult = $this->individualBasicDetailService->search($q);

        $this->assertCount(1, $searchResult);
        $this->assertEquals($q, $searchResult->first()->first_name);

        // Assertions done, cleanup the database.
        $this->truncate_test_db();

        // Check if the database has been successfully truncated. Testing for one table only.
        $initialCount = DB::table('employees')->count();
        $this->assertEquals(0, $initialCount, 'Database table should be empty after refresh.');

    }

    public function generate_test_data(): array
    {
        // Generate random data
        // @todo: Update as we add new models.
        $testIndividual = IndividualBasicDetail::factory()->make()->toArray();
        $testEmployee = Employee::factory()->make()->toArray();
        $testAddress = IndividualAddress::factory()->make()->toArray();
        $testContactInfo = IndividualContactInfo::factory()->make()->toArray();
        $testFamily = IndividualFamily::factory()->make()->toArray();

        // Combine data and structure it so that it is similar to the request body
        $requestData = [
            'individual' => $testIndividual,
            'employee' => [$testEmployee],
            'individual_address' => [$testAddress],
            'individual_contact_info' => [$testContactInfo],
            'individual_family' => [$testFamily],
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

        // Get first record in hasMany relationship.
        // @todo: Update as we add new models.
        $firstFamily = $individual->individualFamily()->first();

        $newInfo = $this->generate_test_data();
        // Add ids
        $newInfo['employee'][0]['id'] = $individual->employee->id;
        $newInfo['individual_address'][0]['id'] = $individual->individualAddress->id;
        $newInfo['individual_contact_info'][0]['id'] = $individual->individualContactInfo->id;
        $newInfo['individual_family'][0]['id'] = $firstFamily->id;
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
