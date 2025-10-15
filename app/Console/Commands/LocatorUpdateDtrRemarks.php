<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use Log;
use Str;

class LocatorUpdateDtrRemarks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locator-update-dtr-remarks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the DTR employee remarks and appends the end of day locator slip logs information.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // get the list of employee with locators for the day
        // get the dtrs corresponding to that employee for that day
        // if there is no dtr for that day, create one with the new remarks
        // update remarks
        // the new remarks will be taken from locator_slip_loggers (combining the following fields: locator_slip_no, approved_for, purpose, destination, time_out, & time_in)
        Log::info('Starting to update the DTRs of employees with locator slip logs for the day...');

        $today = now()->yesterday()->toDateString(); // get yesterday to process the data of the day that just ended.
        $updatedDtrCount = 0;
        $createdDtrCount = 0;

        $locatorData = DB::table('locator_slips as ls')
            ->join('locator_slip_loggers as lsl', 'ls.id', 'lsl.locator_slip_id')
            ->select('ls.employee_id', 'ls.locator_slip_no', 'lsl.approved_for', 'lsl.purpose', 'lsl.destination', 'lsl.time_out', 'lsl.time_in')
            ->whereDate('lsl.date', $today)
            ->get();

        if ($locatorData->isEmpty()) {
            Log::info("No employees found with locator slips for today: {$today}.");

            return 0;
        }

        $remarksMap = $locatorData->groupBy('employee_id')->map(function ($loggers, $employeeId) {
            $segments = [];
            foreach ($loggers as $item) {
                $headlineApproval = Str::headline($item->approved_for);
                $lsNo = $item->locator_slip_no ? "LS No.: $item->locator_slip_no" : '';
                $timeOut = $item->time_out ?? '';
                $timeIn = $item->time_in ?? '';
                $combinedTime = "($timeOut - $timeIn)";
                $segments[] = "$headlineApproval $combinedTime $lsNo - $item->purpose at $item->destination";
            }

            return implode(' | ', $segments);
        });

        $employeeLocators = $remarksMap->keys();

        Log::info('Updating '.$employeeLocators->count().' employee DTRs...');

        $existingDtrs = DB::table('daily_time_records')
            ->whereIn('employee_id', $employeeLocators)
            ->whereDate('date', $today)
            ->get()
            ->keyBy('employee_id');

        $employeesToCreate = collect([]);

        foreach ($employeeLocators as $employeeId) {
            $dynamicRemark = $remarksMap->get($employeeId);

            if ($existingDtrs->has($employeeId)) {
                // DTR exists: APPEND the remarks
                $dtrRecord = $existingDtrs->get($employeeId);
                $existingRemark = $dtrRecord->employee_remarks ?? '';

                if (empty($existingRemark)) {
                    $finalRemark = $dynamicRemark;
                } else {
                    $finalRemark = $existingRemark.' | '.$dynamicRemark;
                }

                DB::table('daily_time_records')
                    ->where('id', $dtrRecord->id)
                    ->update([
                        'employee_remarks' => $finalRemark,
                        'updated_at' => now(),
                    ]);
                $updatedDtrCount++;
            } else {
                // DTR does NOT exist: Prepare data for bulk insert
                $employeesToCreate->push([
                    'employee_id' => $employeeId,
                    'date' => $today,
                    'employee_remarks' => $dynamicRemark,
                    'status' => 'Draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if ($employeesToCreate->isNotEmpty()) {
            DB::table('daily_time_records')->insert($employeesToCreate->toArray());
            $createdDtrCount = $employeesToCreate->count();
        }

        Log::info("Update complete for {$today}.");
        Log::info("DTRs Updated (Remarks Appended): {$updatedDtrCount}");
        Log::info("DTRs Created: {$createdDtrCount}");

        return 0;

    }
}
