<?php

namespace App\Console\Commands;

use App\Enums\ApprovalType;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;
use Log;

class LocatorComputeTime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locator-compute-time';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Computes the end of day locator slips personal time.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Log::info('Starting to compute the personal time for the locator slip logs of the day...');

        $today = now()->yesterday()->toDateString(); // get yesterday to process the data of the day that just ended.
        $updatedLogsCount = 0;
        $auxUpdatedCount = 0;

        $locatorData = DB::table('locator_slips as ls')
            ->join('locator_slip_loggers as lsl', 'ls.id', 'lsl.locator_slip_id')
            ->select(
                'ls.id AS locator_id',
                'ls.employee_id',
                'ls.auxiliary_wellness',

                'lsl.id AS log_id',
                'lsl.approved_for',
                'lsl.purpose',
                'lsl.destination',
                'lsl.time_out',
                'lsl.time_in',
                'lsl.duration')
            ->whereDate('lsl.date', $today)
            ->get();

        if ($locatorData->isEmpty()) {
            Log::info("No employees found with locator slips for today: {$today}.");

            return 0;
        }

        $logDurations = [];
        foreach ($locatorData as $log) {
            // For Personal Time, check if it falls under lunch time.
            // If true, remove the duration that falls under lunch time.
            // Else, set all as personal time.

            // Compare times with lunch
            // if time_out < lunch_start:
            //      t1 = time_out.diffInMinutes(lunch_start)
            // if time_in > lunch_end:
            //      t2 = time_in.diffInMinutes(lunch_end)
            // duration = t1 + t2

            $headlineApproval = $log->approved_for;
            $purpose = $log->purpose;

            $timeOut = $log->time_out ? Carbon::parse("$today $log->time_out") : null;
            $timeIn = $log->time_in ? Carbon::parse("$today $log->time_in") : null;

            // Skip records where they dont have time in / out
            if ($timeIn === null || $timeOut === null) {
                continue;
            }

            /* -------------------------------------------------------------------------- */
            /*                            Process Personal Time */
            /* -------------------------------------------------------------------------- */
            if ($headlineApproval === ApprovalType::PERSONAL_TIME->value) {
                $computedDuration = $this->computeDuration($timeOut, $timeIn);
                $logDurations[] = [
                    'id' => $log->log_id,
                    'duration' => $computedDuration,
                ];

                continue;
            }

            /* -------------------------------------------------------------------------- */
            /*                            Process Official Time */
            /* -------------------------------------------------------------------------- */
            elseif ($headlineApproval === ApprovalType::OFFICIAL_TIME->value) {

                /* ----------------------- Process Auxiliary Wellness ----------------------- */
                if ($purpose === 'Auxiliary Wellness') {
                    $computedDuration = $this->computeDuration($timeOut, $timeIn);
                    $remainingAux = $log->auxiliary_wellness;

                    // Once the auxiliary wellness reaches zero, the excess will be set as personal time
                    $updatedAux = max(0, $remainingAux - $computedDuration);
                    $usedDuration = $remainingAux - $updatedAux;
                    $excessDuration = $computedDuration - $usedDuration;

                    $logDurations[] = [
                        'id' => $log->log_id,
                        'duration' => $excessDuration,
                    ];

                    DB::table('locator_slips')
                        ->where('id', $log->locator_id)
                        ->update([
                            'auxiliary_wellness' => $updatedAux,
                            'updated_at' => now(),
                        ]);

                    $auxUpdatedCount++;
                }
            }
        }

        if (! empty($logDurations)) {
            Log::info('Updating '.count($logDurations).' locator slip logs using batch update...');

            $ids = collect($logDurations)->pluck('id')->all();
            $updated_at = now()->toDateTimeString();

            $duration_case = 'CASE ';
            foreach ($logDurations as $record) {
                // Use a precise value with casting for safety in raw SQL
                $duration_case .= "WHEN id = {$record['id']} THEN CAST({$record['duration']} AS DECIMAL(10, 4)) ";
            }
            $duration_case .= 'END';

            $updatedLogsCount = DB::table('locator_slip_loggers')
                ->whereIn('id', $ids)
                ->update([
                    'duration' => DB::raw($duration_case),
                    'updated_at' => $updated_at,
                ]);
        }

        Log::info("Update complete for date: {$today}.");
        Log::info("Total Locator Slip Logs Updated: {$updatedLogsCount}");
        Log::info("Locator Slip Logs Updated (Auxiliary Wellness Updated): {$auxUpdatedCount}");

        return 0;

    }

    /**
     * Computes the difference of time in and time out and disregards lunch time for the computation.
     *
     * @return float|int
     */
    public function computeDuration(Carbon $timeOut, Carbon $timeIn): float
    {
        $lunchStart = $timeOut->copy()->setTime(12, 0, 0);
        $lunchEnd = $timeOut->copy()->setTime(13, 0, 0);
        $t1 = 0;
        $t2 = 0;

        if (($timeOut->isBefore($lunchStart) && $timeIn->isBefore($lunchStart)) ||
            ($timeOut->isAfter($lunchEnd) && $timeIn->isAfter($lunchEnd))
        ) {
            return $timeOut->diffInMinutes($timeIn) / 60;
        }
        if ($timeOut->lessThan($lunchStart)) {
            $t1 = $timeOut->diffInMinutes($lunchStart);
        }
        if ($timeIn->greaterThan($lunchEnd)) {
            $t2 = $timeIn->diffInMinutes($lunchEnd);
        }

        return ($t1 + $t2) / 60;
    }
}
