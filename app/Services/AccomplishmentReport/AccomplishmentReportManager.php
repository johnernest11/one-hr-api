<?php

namespace App\Services\AccomplishmentReport;

use App\Enums\ARStatus;
use App\Enums\PaginationType;
use App\Models\AccomplishmentReport;
use App\Models\ARRows;
use App\Models\User;
use App\Traits\Services\CanBuildPagination;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\TemplateProcessor;

use function Laravel\Prompts\error;

class AccomplishmentReportManager
{
    use CanBuildPagination;
    //use CanResolveModelFromId;

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
     *
     *  @todo LOGIC CAN BE BETTER
     */
    public function create(User $user, array $arInfo): AccomplishmentReport
    {
        return DB::transaction(function () use ($user, $arInfo) {
            $arInfo['user_profile_id'] = $user->id;
            $arInfo['status'] = ARStatus::DRAFT; // Set status to draft by default

            $exemptedAttributes = ['rows'];
            // initialize values then create record
            $ar = AccomplishmentReport::create(Arr::except($arInfo, $exemptedAttributes));

            // @todo Improved check:
            if (isset($arInfo['rows']) && is_array($arInfo['rows']) && ! empty($arInfo['rows'])) {
                $ar->rows()->createMany($arInfo['rows']);
            } elseif (isset($arInfo['rows']) && ! is_array($arInfo['rows'])) {
                // Handle the error, e.g., log it or throw an exception
                dd('Rows data is not an array.  Data received: '.print_r($arInfo['rows'], true));
                throw new \Exception('Invalid rows data. Rows must be an array.'); // Or a custom exception
            }

            return $ar;
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    public function generate(AccomplishmentReport $accomplishmentReport)
    {
        $templateProcessor = new TemplateProcessor(storage_path('assets\TEMPLATE - Accomplishment-Report.docx'));

        // Process report data
        $userInfo = $accomplishmentReport->userProfile;

        // Set period
        $templateProcessor->setValue('period', $accomplishmentReport->period);

        // Set Names
        $fullName = $userInfo->full_name_initial;
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

        $templateProcessor->saveAs(storage_path('AccomplishmentReport.docx'));

        return response()->download(storage_path('AccomplishmentReport.docx'));
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
                    $row = ARRows::where('accomplishment_report_id', '=', $accomplishmentReport->id)->find($newRowInfo['id']);
                    if ($row) {
                        $row->update(Arr::except($newRowInfo, ['id']));
                    }
                }
            }

            //@todo handle new rows here

            return $accomplishmentReport->fresh('rows');
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }
}
