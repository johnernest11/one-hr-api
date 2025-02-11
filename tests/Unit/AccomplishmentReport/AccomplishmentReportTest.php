<?php

namespace Tests\Unit\AccomplishmentReport;

use App\Models\AccomplishmentReport;
use App\Models\ARRows;
use App\Services\AccomplishmentReport\AccomplishmentReportManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class AccomplishmentReportTest extends TestCase
{
    use RefreshDatabase;

    private AccomplishmentReportManager $aRManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->aRManager = new AccomplishmentReportManager();
    }

    /**
     * Test if an Accomplishment Report will be created via aRManager
     */
    public function test_can_create_accomplishment_report(): void
    {
        $user = $this->produceUsers();

        $data = AccomplishmentReport::factory()->raw();
        $rows[] = ARRows::factory(3)->raw();
        foreach ($rows as $row) {
            $data['rows'] = $row;
        }
        $this->aRManager->create($user, $data);
        $this->assertDatabaseCount('accomplishment_reports', 1);

        // Should be able to also create rows with the accomplishment report created.
        $this->assertDatabaseCount('a_r_rows', 3);
    }

    /**
     * Test if an Accomplishment Report can be edited via aRManager
     */
    public function test_can_edit_accomplishment_report(): void
    {
        $ar = AccomplishmentReport::factory()->hasProfile()->isDraft()->create();
        $this->assertDatabaseCount('accomplishment_reports', 1);
        $this->assertDatabaseCount('a_r_rows', 3); // three rows are automatically created per AR generation

        $newInfo = ['status' => 'done'];

        $updatedAr = $this->aRManager->update($ar, $newInfo);
        $this->assertEquals('done', $updatedAr->status);

    }

    /**
     * Test if an Accomplishment Report and it's rows can be edited via aRManager
     */
    public function test_can_cascade_updates_on_rows(): void
    {
        $ar = AccomplishmentReport::factory()->hasProfile()->isDraft()->create();
        $this->assertDatabaseCount('accomplishment_reports', 1);
        $this->assertDatabaseCount('a_r_rows', 3); // three rows are automatically created per AR generation

        $rows = ARRows::first();
        $newInfo['rows'][]['highlights'] = 'Updated';
        $newInfo['rows'][0]['id'] = $rows->id;

        $updatedAr = $this->aRManager->update($ar, $newInfo);
        $updatedRow = ARRows::find($rows->id);
        $this->assertEquals('Updated', $updatedRow->highlights);
    }

    /**
     * Test if all accomplishment reports can be viewed via aRManager
     */
    public function test_can_view_all_accomplishment_reports(): void
    {
        $ar = AccomplishmentReport::factory(5)->hasProfile()->isDraft()->create();
        $this->assertDatabaseCount('accomplishment_reports', 5);
        $this->assertDatabaseCount('a_r_rows', 15);

        $paginatedResults = $this->aRManager->all();
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResults);
    }

    /**
     * Test if an accomplishment report can be viewed via aRManager by it's ID
     */
    public function test_can_view_report_by_id(): void
    {
        $ar = AccomplishmentReport::factory(5)->hasProfile()->isDraft()->create();
        $this->assertDatabaseCount('accomplishment_reports', 5);
        $this->assertDatabaseCount('a_r_rows', 15);

        $searchForThis = AccomplishmentReport::find($ar->first()->id);
        $paginatedResults = $this->aRManager->read($searchForThis);
        $this->assertEquals($searchForThis->id, $paginatedResults->id);
    }

    /**
     * Test if accomplishment reports can be filtered by status via aRManager
     */
    public function test_can_filter_based_on_status(): void
    {
        $arDraft = AccomplishmentReport::factory(3)->hasProfile()->isDraft()->create();
        $arDone = AccomplishmentReport::factory(2)->hasProfile()->isDone()->create();
        $this->assertDatabaseCount('accomplishment_reports', 5);
        $this->assertDatabaseCount('a_r_rows', 15);

        $request = new Request();
        $request->replace(['status' => 'done']);
        app()->instance('request', $request);

        $paginatedResults = $this->aRManager->all();

        $this->assertEquals(2, $paginatedResults->total());
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResults);
    }

    /**
     * Test if accomplishment reports can be generated into docx t via aRManager
     */
    public function test_can_generate_docx(): void
    {
        $ar = AccomplishmentReport::factory(5)->hasProfile()->isDraft()->create();
        $this->assertDatabaseCount('accomplishment_reports', 5);
        $this->assertDatabaseCount('a_r_rows', 15);

        $testReport = $ar->first();
        $response = $this->aRManager->generate($testReport);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
    }
}
