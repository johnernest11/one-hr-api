<?php

namespace Tests\Unit\AccomplishmentReport;

use App\Models\AccomplishmentReport;
use App\Models\ARRows;
use App\Models\User;
use App\Services\AccomplishmentReport\AccomplishmentReportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AccomplishmentReportUnitTest extends TestCase
{
    use RefreshDatabase;

    private AccomplishmentReportService $aRService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->aRService = new AccomplishmentReportService;
        $this->user = $this->produceUsers();
    }

    /**
     * Test if an Accomplishment Report will be created via aRService
     */
    public function test_can_create_accomplishment_report(): void
    {

        $data = AccomplishmentReport::factory()->raw();
        $rows[] = ARRows::factory(3)->raw();
        foreach ($rows as $row) {
            $data['rows'] = $row;
        }
        $this->aRService->create($this->user, $data);
        $this->assertDatabaseCount('accomplishment_reports', 1);

        // Should be able to also create rows with the accomplishment report created.
        $this->assertDatabaseCount('a_r_rows', 3);
    }

    /**
     * Test if an Accomplishment Report can be edited via aRService
     */
    public function test_can_edit_accomplishment_report(): void
    {
        $ar = AccomplishmentReport::factory()->hasProfile($this->user)->isDraft()->create();
        $this->assertDatabaseCount('accomplishment_reports', 1);
        $this->assertDatabaseCount('a_r_rows', 3); // three rows are automatically created per AR generation

        $newInfo = ['status' => 'done'];

        $updatedAr = $this->aRService->update($ar, $newInfo);
        $this->assertEquals('done', $updatedAr->status);

    }

    /**
     * Test if an Accomplishment Report and it's rows can be edited via aRService
     */
    public function test_can_cascade_updates_on_rows(): void
    {
        $ar = AccomplishmentReport::factory()->hasProfile($this->user)->isDraft()->create();
        $this->assertDatabaseCount('accomplishment_reports', 1);
        $this->assertDatabaseCount('a_r_rows', 3); // three rows are automatically created per AR generation

        $rows = ARRows::first();
        $newInfo['rows'][]['highlights'] = 'Updated';
        $newInfo['rows'][0]['id'] = $rows->id;

        $updatedAr = $this->aRService->update($ar, $newInfo);
        $updatedRow = ARRows::find($rows->id);
        $this->assertEquals('Updated', $updatedRow->highlights);
    }

    /**
     * Test if all accomplishment reports can be viewed via aRService
     */
    public function test_can_view_all_accomplishment_reports(): void
    {
        $ar = AccomplishmentReport::factory(5)->hasProfile($this->user)->isDraft()->create();
        $this->assertDatabaseCount('accomplishment_reports', 5);
        $this->assertDatabaseCount('a_r_rows', 15);

        $this->actingAs($this->user); // simulate user auth
        $paginatedResults = $this->aRService->all();
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResults);
    }

    /**
     * Test if an accomplishment report can be viewed via aRService by it's ID
     */
    public function test_can_view_report_by_id(): void
    {
        $ar = AccomplishmentReport::factory(5)->hasProfile($this->user)->isDraft()->create();
        $this->assertDatabaseCount('accomplishment_reports', 5);
        $this->assertDatabaseCount('a_r_rows', 15);

        $searchForThis = AccomplishmentReport::find($ar->first()->id);
        $paginatedResults = $this->aRService->read($searchForThis);
        $this->assertEquals($searchForThis->id, $paginatedResults->id);
    }

    /**
     * Test if accomplishment reports can be filtered by status via aRService
     */
    public function test_can_filter_based_on_status(): void
    {
        $arDraft = AccomplishmentReport::factory(3)->hasProfile($this->user)->isDraft()->create();
        $arDone = AccomplishmentReport::factory(2)->hasProfile($this->user)->isDone()->create();
        $this->assertDatabaseCount('accomplishment_reports', 5);
        $this->assertDatabaseCount('a_r_rows', 15);

        $request = new Request;
        $request->replace(['status' => 'done']);
        app()->instance('request', $request);

        $this->actingAs($this->user); // simulate user auth
        $paginatedResults = $this->aRService->all();

        $this->assertEquals(2, $paginatedResults->total());
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResults);
    }

    public function test_can_search_ar(): void
    {

        $initialItem = AccomplishmentReport::factory()->hasProfile($this->user)->isDraft()->create();
        $this->assertDatabaseCount('accomplishment_reports', 1);

        // Update Number for easier search
        $newInfo = ['period' => 'MAy 1-15 2023'];
        $initialItem->update($newInfo);

        $q = 'MAy';
        $result = $this->aRService->search($q);
        // Compare result to the expected types of response from the service and the new number should match with the query
        if ($result instanceof Collection || $result instanceof Paginator || $result instanceof LengthAwarePaginator || $result instanceof CursorPaginator) {
            $aRs = ($result instanceof Collection) ? $result : $result->items();

            foreach ($aRs as $AR) {
                $this->assertStringContainsString($q, $AR['period']);
            }
        }
    }

    /**
     * Test if accomplishment reports can be generated into docx t via aRService
     */
    public function test_can_generate_docx(): void
    {
        $ar = AccomplishmentReport::factory(5)->hasProfile($this->user)->isDraft()->create();
        $this->assertDatabaseCount('accomplishment_reports', 5);
        $this->assertDatabaseCount('a_r_rows', 15);

        $testReport = $ar->first();
        $userInfo = $testReport->userProfile;
        $response = $this->aRService->generate($testReport);

        $this->assertNotEmpty($response['fileContent']);

        // filename should match format
        $fileNameFormat = "$testReport->period-$userInfo->initials-AccomplishmentReport.docx";
        $this->assertEquals($response['fileName'], $fileNameFormat);
    }
}
