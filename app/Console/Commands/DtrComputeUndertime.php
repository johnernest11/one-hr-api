<?php

namespace App\Console\Commands;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\DailyTimeRecords\TimeLog;
use App\Models\LocatorSlip\LocatorSlip;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Log;

class DtrComputeUndertime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dtr-compute-ut';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Computes the end of day daily time records undertime.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Log::info('Starting to compute the under time for the daily time records of the day...');

        $today = now()->yesterday()->toDateString(); // get yesterday to process the data of the day that just ended.
        $todayCarbon = Carbon::now()->yesterday();

        if ($todayCarbon->isWeekend()) {
            Log::info('No undertime computation during weekends... Ending scheduler...');

            return 0;
        }

        $updatedDTRs = 0;

        $dtrs = DailyTimeRecord::with('employee', 'timeLog')
            ->whereDate('date', $today)
            ->get();

        $employeeIds = $dtrs->pluck('employee_id')->unique();

        $locatorData = LocatorSlip::with(['locatorSlipLogger' => function ($query) use ($today) {
            $query->whereDate('date', $today);
        }])
            ->whereIn('employee_id', $employeeIds)
            ->get();

        if ($dtrs->isEmpty()) {
            Log::info("No daily time records for today: {$today}.");

            return 0;
        }

        /* -------------------------------------------------------------------------- */
        /*                              Compute Undertime */
        /* -------------------------------------------------------------------------- */

        $updates = [];
        foreach ($dtrs as $r) {
            $slots = $this->resolveDTRSlots($r->timeLog);

            $result = $this->getCreditedWorkHours($slots, $todayCarbon);
            $finalAWH = $result['totalWorked'];

            $personalTimePenalty = $this->calculateEmployeePersonalTimePenalty($r->employee_id, $locatorData);

            $finalAWH -= $personalTimePenalty;

            $requiredHours = 8.0;
            $undertimeHours = max(0.0, $requiredHours - $finalAWH);

            if ($undertimeHours > 0) {
                $updates[] = [
                    'id' => $r->id,
                    'undertime_hours' => $undertimeHours,
                ];
            }

        }

        /* -------------------------------------------------------------------------- */
        /*                              Batch Update DTRs */
        /* -------------------------------------------------------------------------- */

        if (empty($updates)) {
            Log::info('No undertime computed for today...');
        }

        if (! empty($updates)) {
            $ids = collect($updates)->pluck('id')->all();
            $updated_at = now()->toDateTimeString();

            $ut_case = 'CASE ';
            foreach ($updates as $record) {
                // Use a precise value with casting for safety in raw SQL
                $ut_case .= "WHEN id = {$record['id']} THEN CAST({$record['undertime_hours']} AS DECIMAL(5, 2)) ";
            }
            $ut_case .= 'END';

            $updatedDTRs = DB::table('daily_time_records')
                ->whereIn('id', $ids)
                ->update([
                    'ut' => DB::raw($ut_case),
                    'updated_at' => $updated_at,
                ]);
        }

        Log::info("Update complete for date: {$today}.");
        Log::info("Total DTRs updated: {$updatedDTRs}.");

        return 0;

    }

    /**
     * @todo: Refactor code such that this function is combined with the original somewhere.
     *
     * Originally from daily_time_record.blade.php.
     * Determines the in and out slots for the DTR.
     *
     * @param  Collection<TimeLog>  $timeLogs  The raw time logs for a single day.
     */
    private function resolveDTRSlots(Collection $timeLogs): array
    {
        $timeLogs = collect($timeLogs);

        $slots = ['in1' => null, 'out1' => null, 'in2' => null, 'out2' => null];

        if ($timeLogs->isEmpty()) {
            return $slots;
        }

        $sorted = $timeLogs->sortBy(function ($log) {
            return strtotime($log->date.' '.$log->scanned_time);
        })->values();

        $getHour = fn ($log) => (int) date('H', strtotime($log->scanned_time));

        // IN1: earliest 6–12
        $slots['in1'] = $sorted->first(fn ($log) => ($h = $getHour($log)) >= 6 && $h < 12);

        // OUT1: first 12–13
        $slots['out1'] = $sorted
            ->filter(fn ($log) => ($h = $getHour($log)) >= 11 && $h <= 13) // consider around 11 AM–1 PM
            ->sortBy(fn ($log) => abs(strtotime($log->scanned_time) - strtotime('12:00')))
            ->first();

        // IN2: first log between 12–14 and 15 mins after OUT1
        if ($slots['out1']) {
            $out1Time = strtotime($slots['out1']->scanned_time);
            $slots['in2'] = $sorted->first(function ($log) use ($out1Time) {
                $time = strtotime($log->scanned_time);
                $h = (int) date('H', $time);

                return $h >= 12 && $h < 14 && $time >= $out1Time + (1 * 60);
            });
        }

        // OUT2: last ≥ 14h
        $slots['out2'] = $sorted->filter(fn ($log) => (int) date('H', strtotime($log->scanned_time)) >= 14)
            ->sortByDesc(fn ($log) => strtotime($log->scanned_time))
            ->first();

        return $slots;
    }

    /**
     * Calculates employee's work hours, taking into consideration the tardiness, half days
     *   and flexi-time during mondays.
     */
    private function getCreditedWorkHours(array $slots, Carbon $dtrDate): array
    {
        $in1 = $slots['in1'] ? $dtrDate->copy()->setTimeFromTimeString($slots['in1']->scanned_time) : null;
        $out1 = $slots['out1'] ? $dtrDate->copy()->setTimeFromTimeString($slots['out1']->scanned_time) : null;
        $in2 = $slots['in2'] ? $dtrDate->copy()->setTimeFromTimeString($slots['in2']->scanned_time) : null;
        $out2 = $slots['out2'] ? $dtrDate->copy()->setTimeFromTimeString($slots['out2']->scanned_time) : null;

        $lunchStart = $dtrDate->copy()->setTimeFromTimeString('12:00');
        $lunchEnd = $dtrDate->copy()->setTimeFromTimeString('13:00');

        // Cut offs for late and half day based on day of the week
        $lateCutoff = $dtrDate->copy()->setTimeFromTimeString($dtrDate->dayOfWeek === Carbon::MONDAY ? '08:00' : '09:00');
        $halfDayCutoff = $dtrDate->copy()->setTimeFromTimeString($dtrDate->dayOfWeek === Carbon::MONDAY ? '09:00' : '10:00');

        $creditStart = $in1;
        $requiredEnd = null;

        if (! $in1 || $in1->greaterThan($halfDayCutoff)) {
            // Absence or automatic 4-hour penalty scenario
            $creditStart = null;
            $requiredEnd = null;
        } elseif ($in1->greaterThan($lateCutoff)) {
            // Scenario: LATE (e.g., 8:05 AM Mon). Credit starts at actual time in.
            $creditStart = $in1;
        } else {
            // Scenario: ON TIME (e.g., 8:22 AM Tue). Credit starts at actual time in.
            $creditStart = $in1;
        }

        // Calculate the Required End Time based on the Credited Start Time
        if ($creditStart) {
            // Shift is 8 hours + 1 hour lunch = 9 hours total duration
            $requiredEnd = $creditStart->copy()->addHours(9);
        }

        // Helper function to compute hours
        $computeHours = function (?Carbon $start, ?Carbon $end, ?Carbon $requiredEnd, Carbon $lunchStart, Carbon $lunchEnd): float {
            if (! $start || ! $end || ! $requiredEnd) {
                return 0.0;
            }

            $end = $end->min($requiredEnd);

            if ($end->lessThanOrEqualTo($start)) {
                return 0.0;
            }

            $mins = $end->diffInMinutes($start);
            $hours = $mins / 60;

            // Remove lunch overlap time
            $overlapStartCarbon = $start->max($lunchStart);
            $overlapEndCarbon = $end->min($lunchEnd);

            if ($overlapEndCarbon->greaterThan($overlapStartCarbon)) {
                $overlapMins = $overlapEndCarbon->diffInMinutes($overlapStartCarbon);
                $hours -= $overlapMins / 60;
            }

            return $hours;
        };

        $worked = 0.0;

        $worked += $computeHours($creditStart, $out1, $requiredEnd, $lunchStart, $lunchEnd);
        $worked += $computeHours($in2, $out2, $requiredEnd, $lunchStart, $lunchEnd);

        return ['totalWorked' => round($worked, 2)];
    }

    /**
     * Calculates the employee's personal time in hours.
     * If more than 2 hours has been consumed, will return 4 hours signifying half day.
     */
    private function calculateEmployeePersonalTimePenalty(int|Employee $employeeId, Collection $locators): float
    {
        $employeeLocators = $locators->where('employee_id', $employeeId);
        $sumPT = 0;

        foreach ($employeeLocators as $l) {
            $sumPT += $l->locatorSlipLogger->sum('duration');
        }

        if ($sumPT > 2) {
            return 4;
        }

        return $sumPT;
    }
}
