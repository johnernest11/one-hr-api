<?php

namespace App\Services\AccomplishmentReport;

use App\Enums\ARStatus;
use App\Enums\PaginationType;
use App\Models\AccomplishmentReport;
use App\Models\ARRows;
use App\Models\User;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\TemplateProcessor;
use Storage;

class AccomplishmentReportService implements AccomplishmentReportManager
{
    use CanBuildPagination;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    /** {@inheritDoc} */
    public function all(): LengthAwarePaginator
    {
        /** @var Builder $accomplishmentReports */
        $query = AccomplishmentReport::filtered();
        $query->orderBy('id', 'desc');

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function create(User $user, array $arInfo): AccomplishmentReport
    {
        return DB::transaction(function () use ($user, $arInfo) {
            $userProfile = $user->userProfile;
            $arInfo['user_profile_id'] = $userProfile->id;
            $arInfo['status'] = ARStatus::DRAFT; // Set status to draft by default
            $exemptedAttributes = ['rows'];
            // initialize values then create record
            $ar = AccomplishmentReport::create(Arr::except($arInfo, $exemptedAttributes));

            // rows has to be array and not empty
            if (isset($arInfo['rows']) && is_array($arInfo['rows']) && ! empty($arInfo['rows'])) {
                $ar->rows()->createMany($arInfo['rows']);
            }

            return $ar;
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    public function generate(AccomplishmentReport $accomplishmentReport): array
    {
        $templatePath = Storage::disk('assets')->path('TEMPLATE - Accomplishment-Report.docx');
        $templateProcessor = new TemplateProcessor($templatePath);

        // Helper function to sanitize Special Characters
        $sanitize = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // Process report data
        $userInfo = $accomplishmentReport->userProfile->individualBasicDetail;

        // Set period
        $templateProcessor->setValue('period', $sanitize($accomplishmentReport->period));

        // Set Names
        $templateProcessor->setValues([
            'firstName' => $sanitize($userInfo->first_name),
            'middleName' => $sanitize($userInfo->middle_name),
            'lastName' => $sanitize($userInfo->last_name),
            'fullName' => $sanitize(trim(($userInfo?->first_name ?? '').' '.($userInfo?->middle_name ?? '').' '.($userInfo?->last_name ?? '').' '.($userInfo?->ext_name?->value ?? ''))),
        ]);

        // Set Position and Designation
        $templateProcessor->setValues([
            'position' => $sanitize($userInfo->employee->item->position->title),
            'designation' => $sanitize($userInfo->employee->item->position->parenthetical_title),
        ]);

        // Set ODSU
        $templateProcessor->setValue('odsu', $sanitize(trim(($userInfo?->employee->division->name ?? '').' - '.($userInfo?->employee->sectionOrUnit->name ?? ''))));

        // Set Weeks and Activities
        $values = [];

        foreach ($accomplishmentReport->rows as $row) {
            $values[] = [
                'weekNum' => $sanitize($row['week_num']),
                'datesInWeek' => $sanitize($row['dates_in_week']),
                'activities' => $sanitize($row['specific_activity']),
                'highlights' => $sanitize($row['highlights']),
            ];
        }

        $templateProcessor->cloneRowAndSetValues('weekNum', $values);

        // Set Supervisor Notes
        $templateProcessor->setValue('supervisorNotes', $sanitize($accomplishmentReport->supervisor_notes));

        // Set Officer Information
        $templateProcessor->setValues([
            'certifyingOfficer' => 'SECTION HEAD NAME',
            'officerPosition' => 'POSITION/DESIGNATION',
        ]);

        $fileName = "$accomplishmentReport->period-$userInfo->last_name-AccomplishmentReport.docx";

        ob_start();
        $templateProcessor->saveAs('php://output');
        $fileContent = ob_get_clean();

        return [
            'fileContent' => $fileContent,
            'fileName' => $fileName,
        ];
    }

    /** {@inheritDoc} */
    public function read(AccomplishmentReport $accomplishmentReport): AccomplishmentReport
    {
        return $accomplishmentReport->load('rows');
    }

    /**
     * {@inheritDoc}
     */
    public function update(AccomplishmentReport $accomplishmentReport, array $newReportInfo): AccomplishmentReport
    {
        return DB::transaction(function () use ($accomplishmentReport, $newReportInfo) {
            $exemptedAttributes = [];

            if (array_key_exists('rows', $newReportInfo)) {
                $exemptedAttributes = ['rows'];
            }

            $accomplishmentReport->update(Arr::except($newReportInfo, $exemptedAttributes));

            if (array_key_exists('rows', $newReportInfo)) {
                foreach ($newReportInfo['rows'] as $newRowInfo) {
                    // Check if id exists.
                    if (isset($newRowInfo['id'])) {
                        $row = ARRows::where('accomplishment_report_id', '=', $accomplishmentReport->id)->find($newRowInfo['id']);
                        if ($row) {
                            $row->update(Arr::except($newRowInfo, ['id']));
                        }
                    }
                    // If not, create new row
                    else {
                        $createNewRow = new ARRows($newRowInfo);
                        $createNewRow->accomplishment_report_id = $accomplishmentReport->id;
                        $createNewRow->save();
                    }

                }
            }

            return $accomplishmentReport->fresh('rows');
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /**
     * {@inheritDoc}
     */
    public function search(
        string $term,
        ?PaginationType $pagination = null
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator {
        $items = AccomplishmentReport::query()->where('period', 'like', "%$term%");

        return $this->buildPagination($pagination, $items);
    }
}
