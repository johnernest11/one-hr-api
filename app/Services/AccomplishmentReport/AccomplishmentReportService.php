<?php

namespace App\Services\AccomplishmentReport;

use App\Enums\ARStatus;
use App\Enums\PaginationType;
use App\Models\AccomplishmentReport;
use App\Models\ARRows;
use App\Models\User;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function create(User $user, array $arInfo): AccomplishmentReport
    {
        return DB::transaction(function () use ($user, $arInfo) {
            $arInfo['user_profile_id'] = $user->id;
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

        // Process report data
        $userInfo = $accomplishmentReport->userProfile;

        // Set period
        $templateProcessor->setValue('period', $accomplishmentReport->period);

        // Set Names
        $fullName = $userInfo->full_name_w_middle_initial;
        $templateProcessor->setValues([
            'firstName' => $userInfo->first_name,
            'middleName' => $userInfo->middle_name,
            'lastName' => $userInfo->last_name,
            'fullName' => $fullName,
        ]);

        // Set Position and Designation
        $templateProcessor->setValues([
            'position' => 'PLACEHOLDER',
            'designation' => 'PLACEHOLDER',
        ]);

        // Set ODSU
        $templateProcessor->setValue('odsu', 'PLACEHOLDER');

        // Set Weeks and Activities
        $values = [];

        foreach ($accomplishmentReport->rows as $row) {
            $values[] = [
                'weekNum' => $row['week_num'],
                'datesInWeek' => $row['dates_in_week'],
                'activities' => $row['specific_activity'],
                'highlights' => $row['highlights'],
            ];
        }

        $templateProcessor->cloneRowAndSetValues('weekNum', $values);

        // Set Supervisor Notes
        $templateProcessor->setValue('supervisorNotes', $accomplishmentReport->supervisor_notes);

        // Set Officer Information
        $templateProcessor->setValues([
            'certifyingOfficer' => 'PLACEHOLDER',
            'officerPosition' => 'PLACEHOLDER',
        ]);

        $fileName = "$accomplishmentReport->period-$userInfo->initials-AccomplishmentReport.docx";

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
}
