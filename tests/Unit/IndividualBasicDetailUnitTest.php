<?php

namespace Tests\Unit;

use App\Enums\PDSFormType;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualAddress;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\ComprehensiveRecords\IndividualContactInfo;
use App\Models\ComprehensiveRecords\IndividualEducationalBackground;
use App\Models\ComprehensiveRecords\IndividualEligibility;
use App\Models\ComprehensiveRecords\IndividualFamily;
use App\Models\ComprehensiveRecords\IndividualLnd;
use App\Models\ComprehensiveRecords\IndividualMembership;
use App\Models\ComprehensiveRecords\IndividualQuestion;
use App\Models\ComprehensiveRecords\IndividualRecognition;
use App\Models\ComprehensiveRecords\IndividualReference;
use App\Models\ComprehensiveRecords\IndividualSkillsHobby;
use App\Models\ComprehensiveRecords\IndividualVoluntaryWork;
use App\Models\ComprehensiveRecords\IndividualWorkExperience;
use App\Models\User;
use App\Services\ComprehensiveRecords\IndividualBasicDetailService;
use App\Services\DailyTimeRecords\QrCodeManager;
use Arr;
use DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
        $this->individualBasicDetailService = new IndividualBasicDetailService(new IndividualBasicDetail(), $this->app->make(\TheIconic\NameParser\Parser::class), $this->app->make(QrCodeManager::class));
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

    /**
     * Generate test data.
     *
     * @param  $form_type  can be C1, C2, C3, C4 and null. Pass nothing to generate test data to all models.
     */
    public function generate_test_data($form_type = null): array
    {
        // Generate random data
        // @todo: Update as we add new models.
        $testIndividual = IndividualBasicDetail::factory()->make()->toArray();
        $testEmployee = Employee::factory()->make()->toArray();
        $testAddress = IndividualAddress::factory()->make()->toArray();
        $testContactInfo = IndividualContactInfo::factory()->make()->toArray();
        $testFamily = IndividualFamily::factory()->make()->setAppends([])->toArray(); // remove appended attributes for testing.
        $testEducation = IndividualEducationalBackground::factory()->make()->toArray();
        $testEligibility = IndividualEligibility::factory()->make()->toArray();
        $testWorkExperience = IndividualWorkExperience::factory()->make()->toArray();
        $testVoluntaryWork = IndividualVoluntaryWork::factory()->make()->toArray();
        $testLnd = IndividualLnd::factory()->make()->toArray();
        $testSkillsHobby = IndividualSkillsHobby::factory()->make()->toArray();
        $testRecognition = IndividualRecognition::factory()->make()->toArray();
        $testMembership = IndividualMembership::factory()->make()->toArray();
        $testQuestion = IndividualQuestion::factory()->make()->toArray();
        $testReference = IndividualReference::factory()->make()->toArray();

        //@todo Update as new models are added until all forms are completed
        // Combine data and structure it so that it is similar to the request body
        $c1_request = [
            'individual' => $testIndividual,
            'employee' => $testEmployee,
            'individual_address' => [$testAddress],
            'individual_contact_info' => [$testContactInfo],
            'individual_family' => [$testFamily],
            'individual_educational_background' => [$testEducation],
        ];

        $c2_request = [
            'individual_eligibility' => [$testEligibility],
            'individual_work_experience' => [$testWorkExperience],
        ];

        $c3_request = [
            'individual_voluntary_work' => [$testVoluntaryWork],
            'individual_lnd' => [$testLnd],
            'individual_skills_hobby' => [$testSkillsHobby],
            'individual_recognition' => [$testRecognition],
            'individual_membership' => [$testMembership],
        ];

        $c4_request = [
            'individual_question' => [$testQuestion],
            'individual_reference' => [$testReference],
        ];

        $all_request = array_merge(
            Arr::except($c1_request, 'form_type'),
            Arr::except($c2_request, 'form_type'),
            Arr::except($c3_request, 'form_type'),
            Arr::except($c4_request, 'form_type')
        );

        $requestData = match ($form_type) {
            PDSFormType::C1->value => $c1_request,
            PDSFormType::C2->value => $c2_request,
            PDSFormType::C3->value => $c3_request,
            PDSFormType::C4->value => $c4_request,
            default => $all_request,
        };

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
     * Test if C1 can be edited via the service
     */
    public function test_can_edit_c1(): void
    {
        $individual = $this->individualBasicDetailService->store($this->generate_test_data());
        $this->assertDatabaseCount('individual_basic_details', 1);

        // Get first record in hasMany relationship.
        // @todo: Update as we add new models.
        $firstFamily = $individual->individualFamily()->first();
        $firstEducation = $individual->individualEducationalBackground()->first();

        $newInfo = $this->generate_test_data(PDSFormType::C1->value);
        // Add ids
        $newInfo['employee']['id'] = $individual->employee->id;
        $newInfo['individual_address'][0]['id'] = $individual->individualAddress->id;
        $newInfo['individual_contact_info'][0]['id'] = $individual->individualContactInfo->id;
        $newInfo['individual_family'][0]['id'] = $firstFamily->id;
        $newInfo['individual_educational_background'][0]['id'] = $firstEducation->id;
        $updatedData = $this->individualBasicDetailService->update($individual, $newInfo);

        // Check if the data matches the record in the database
        $this->assertDatabaseHas('individual_basic_details', $newInfo['individual']);
        foreach ($newInfo as $key => $value) {
            if ($key == 'individual') {
                $this->assertDatabaseHas('individual_basic_details', $newInfo['individual']);

                continue;
            }
            if ($key == 'employee') { // employee is not nested like the rest of the arrays hence the separate assertion
                $this->assertDatabaseHas(Str::plural($key), $newInfo[$key]); // convert $key to plural form since it is singular to match the table name

                continue;
            }
            $this->assertDatabaseHas(Str::plural($key), $newInfo[$key][0]); // convert $key to plural form since it is singular to match the table name
        }
    }

    /**
     * Test if C2 can be edited via the service
     */
    public function test_can_edit_c2(): void
    {
        $individual = $this->individualBasicDetailService->store($this->generate_test_data());
        $this->assertDatabaseCount('individual_basic_details', 1);

        // Get first record in hasMany relationship.
        // @todo: Update as we add new models.
        $firstEligibility = $individual->individualEligibility()->first();
        $firstWorkExperience = $individual->individualWorkExperience()->first();

        $newInfo = $this->generate_test_data(PDSFormType::C2->value);
        // Add ids
        $newInfo['individual_eligibility'][0]['id'] = $firstEligibility->id;
        $newInfo['individual_work_experience'][0]['id'] = $firstWorkExperience->id;
        $updatedData = $this->individualBasicDetailService->update($individual, $newInfo);

        // Check if the data matches the record in the database
        foreach ($newInfo as $key => $value) {
            $this->assertDatabaseHas(Str::plural($key), $newInfo[$key][0]); // convert $key to plural form since it is singular to match the table name
        }
    }

    /**
     * Test if C3 can be edited via the service
     */
    public function test_can_edit_c3(): void
    {
        $individual = $this->individualBasicDetailService->store($this->generate_test_data());
        $this->assertDatabaseCount('individual_basic_details', 1);

        // Get first record in hasMany relationship.
        // @todo: Update as we add new models.
        $firstVoluntaryWork = $individual->individualVoluntaryWork()->first();
        $firstLnd = $individual->individualLnd()->first();
        $firstSkillsHobby = $individual->individualSkillsHobby()->first();
        $firstRecognition = $individual->individualRecognition()->first();
        $firstMembership = $individual->individualMembership()->first();

        $newInfo = $this->generate_test_data(PDSFormType::C3->value);
        // Add ids
        $newInfo['individual_voluntary_work'][0]['id'] = $firstVoluntaryWork->id;
        $newInfo['individual_lnd'][0]['id'] = $firstLnd->id;
        $newInfo['individual_skills_hobby'][0]['id'] = $firstSkillsHobby->id;
        $newInfo['individual_recognition'][0]['id'] = $firstRecognition->id;
        $newInfo['individual_membership'][0]['id'] = $firstMembership->id;

        $updatedData = $this->individualBasicDetailService->update($individual, $newInfo);

        // Check if the data matches the record in the database
        foreach ($newInfo as $key => $value) {
            $this->assertDatabaseHas(Str::plural($key), $newInfo[$key][0]); // convert $key to plural form since it is singular to match the table name
        }
    }

    /**
     * Test if C4 can be edited via the service
     */
    public function test_can_edit_c4(): void
    {
        $individual = $this->individualBasicDetailService->store($this->generate_test_data());
        $this->assertDatabaseCount('individual_basic_details', 1);

        // Get first record in hasMany relationship.
        // @todo: Update as we add new models.
        $firstReference = $individual->individualReference()->first();

        $newInfo = $this->generate_test_data(PDSFormType::C4->value);
        // Add ids
        $newInfo['individual_question'][0]['id'] = $individual->individualQuestion->id;
        $newInfo['individual_reference'][0]['id'] = $firstReference->id;

        $updatedData = $this->individualBasicDetailService->update($individual, $newInfo);

        // Check if the data matches the record in the database
        foreach ($newInfo as $key => $value) {
            $this->assertDatabaseHas(Str::plural($key), $newInfo[$key][0]); // convert $key to plural form since it is singular to match the table name
        }
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
