<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>DSWD Daily Time Record - Blank Form</title>
    <style>
        /* Basic Styling for the whole page/document */
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.85;
            color: #000;
            padding: 0;
            /* REMOVED PADDING */
            margin: 0;
            /* REMOVED MARGINS */
            width: 8.5in;
            /* Standard paper width */
        }

        /* Container for the single DTR form */
        .dtr-container {
            border: 0px solid #000;
            padding: 1px;
            width: 97%;
            /* Mimics the width of one copy on the page */
            margin: -25;
            margin-top: 10px;
            /* Aligns to the left */
        }

        /* Header Section (DSWD, DAILY TIME RECORD, Period) */
        .header-section {
            text-align: center;
            margin-bottom: 5px;
        }

        .header-section-info {
            text-align: center;
            margin-bottom: 5px;
        }

        .header-section p {
            margin: 0;
            font-size: 11px;
            font-weight: bold;
        }

        /* Employee Info Section */
        .info-table {
            width: 50%;
            margin-bottom: 5px;
        }

        .info-table-footer {
            margin: 16;
            margin-top: 0px;
            margin-bottom: 6px;
            width: 50%;
            float: left;
        }

        .info-table-footer-cert {
            margin: 16;
            margin-top: 0px;
            margin-bottom: 6px;
            width: 50%;
            float: center;
        }



        .info-table td {
            padding: 2px 0;
            border: none;
            text-align: left;
            font-size: 12px;
            font-weight: bold;
        }

        /* Utility line for blanks */
        .line {
            border-bottom: 1px solid #000;
            margin: 0 0 0 5px;
            width: 50%;
            display: inline-block;
        }

        /* Time Entry Table */
        table.time-record {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            table-layout: fixed;
        }

        table.time-record,
        table.time-record th,
        table.time-record td {
            border: 1px solid #000;
            font-size: 10px;
        }

        table.time-record th,
        table.time-record td {
            padding: 0px 0px;
            text-align: center;
        }

        .time-record th {
            font-weight: bold;
            background-color: #f0f0f0;
        }

        /* Column widths based on image structure */
        .days-column {
            width: 18%;
        }

        .ut-ot-td {
            width: 10%;
        }

        .am-pm-data {
            width: 18%;
        }

        /* Footer Section */
        .footer-section {
            margin-top: 0px;
        }

        .certification p {
            margin: 18;
            margin-top: 0px;
            font-size: 12px;
            text-align: center;
            line-height: 1;
        }

        /* Signature and Verification Blocks */
        .signature-block {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;

        }

        .sig-item {
            width: 45%;
            text-align: center;
        }

        .sig-line {
            border-bottom: 1px solid #000;
            padding: 0;
            margin: 0 auto;
            width: 90%;
            height: 5px;
        }

        .label {
            margin-top: 2px;
            font-size: 12px;
        }

        .copy-type {
            font-weight: bold;
            text-align: right;
            margin: 5px 0 0 0;
            font-size: 12px;
        }
    </style>
</head>

<body>
    @php
                    if (!function_exists('resolveDTRSlots')) {
        function resolveDTRSlots($timeLogs)
        {
            $timeLogs = collect($timeLogs);

            $slots = [
                'in1' => null,
                'out1' => null,
                'in2' => null,
                'out2' => null,
            ];

            if ($timeLogs->isEmpty()) {
                return $slots;
            }

            $sorted = $timeLogs
                ->sortBy(function ($log) {
                    return strtotime($log->date . ' ' . $log->scanned_time);
                })
                ->values();

            $getHour = fn($log) => (int) date('H', strtotime($log->scanned_time));

            /**
             * IN1: earliest log between 6AM and before 12PM
             */
            $slots['in1'] = $sorted->first(
                fn($log) => ($h = $getHour($log)) >= 6 && $h < 12
            );

            /**
             * AFTERNOON-ONLY
             *
             * Example:
             * 12:19 PM
             * 06:26 PM
             *
             * Expected:
             * in1  = null
             * out1 = null
             * in2  = 12:19 PM
             * out2 = 06:26 PM
             */
            if (!$slots['in1'] && $sorted->count() >= 2) {
                return [
                    'in1' => null,
                    'out1' => null,
                    'in2' => $sorted->first(),
                    'out2' => $sorted->last(),
                ];
            }

            /**
             * OUT1: closest log around noon
             */
            $slots['out1'] = $sorted
                ->filter(
                    fn($log) => ($h = $getHour($log)) >= 11 && $h <= 13
                )
                ->sortBy(
                    fn($log) => abs(
                        strtotime($log->scanned_time) -
                        strtotime('12:00:00')
                    )
                )
                ->first();

            /**
             * IN2: first log after OUT1
             */
            if ($slots['out1']) {
                $out1Time = strtotime($slots['out1']->scanned_time);

                $slots['in2'] = $sorted->first(function ($log) use ($out1Time) {
                    $time = strtotime($log->scanned_time);
                    $h = (int) date('H', $time);

                    return $h >= 12
                        && $h < 14
                        && $time >= ($out1Time + 60);
                });
            }

            /**
             * OUT2: latest log >= 2PM
             */
            $slots['out2'] = $sorted
                ->filter(
                    fn($log) => (int) date('H', strtotime($log->scanned_time)) >= 14
                )
                ->sortByDesc(
                    fn($log) => strtotime($log->scanned_time)
                )
                ->first();

            return $slots;
        }
                    }

                                        if (!function_exists('computeDTRHours')) {
                                   if (!function_exists('computeDTRHours')) {
                                function computeDTRHours($slots)
                                {
                                    $in1 = $slots['in1'] ? strtotime($slots['in1']->date . ' ' . $slots['in1']->scanned_time) : null;
                                    $out1 = $slots['out1'] ? strtotime($slots['out1']->date . ' ' . $slots['out1']->scanned_time) : null;
                                    $in2 = $slots['in2'] ? strtotime($slots['in2']->date . ' ' . $slots['in2']->scanned_time) : null;
                                    $out2 = $slots['out2'] ? strtotime($slots['out2']->date . ' ' . $slots['out2']->scanned_time) : null;

                                // No morning logs but valid afternoon session
                                if (!$in1 && $in2 && $out2) {
                                    $baseDate = date('Y-m-d', $in2);
                                }
                                // No logs at all
                                elseif (!$in1) {
                                    return [
                                        'ut' => 8,
                                        'ot' => 0,
                                        'totalWorked' => 0,
                                    ];
                                } else {
                                    $baseDate = date('Y-m-d', $in1);
                                }

                                    $isWeekend = in_array(date('w', strtotime($baseDate)), [0, 6]);

                                    $lunchStart = strtotime($baseDate . ' 12:00:00');
                                    $lunchEnd = strtotime($baseDate . ' 13:00:00');

                                    /**
                                     * MORNING SESSION
                                     */
                                    $amStart = $in1;

                                    // fallback: out1 -> out2
                                    $amEnd = $out1 ?: $out2;

                                    if (!$amEnd) {
                                        return [
                                            'ut' => 8,
                                            'ot' => 0,
                                            'totalWorked' => 0,
                                        ];
                                    }

                                    $minStart = strtotime(
                                        $baseDate . ' ' . ($isWeekend ? '08:00:00' : '07:00:00')
                                    );

                                    if ($amStart < $minStart) {
                                        $amStart = $minStart;
                                    }

                                    $adjustedAmEnd = $amEnd;

                                    if ($amEnd > $lunchStart) {
                                        $overlap = min($amEnd, $lunchEnd) - $lunchStart;
                                        $adjustedAmEnd = $amEnd - max($overlap, 0);
                                    }

                                    $morningHours = max(
                                        ($adjustedAmEnd - $amStart) / 3600,
                                        0
                                    );

                                    /**
                                     * AFTERNOON SESSION
                                     */
                                    $afternoonHours = 0;

                                    if ($in2 && $out2 && $out1) {
                                        $pmStart = $in2;
                                        $pmEnd = $out2;

                                        if ($pmStart < $lunchEnd) {
                                            $pmStart = $lunchEnd;
                                        }

                                        if ($pmEnd < $pmStart) {
                                            $pmEnd = $pmStart;
                                        }

                                        $afternoonHours = max(
                                            ($pmEnd - $pmStart) / 3600,
                                            0
                                        );
                                    }

                                    $worked = $morningHours + $afternoonHours;

                                    /**
                                     * HALF-DAY PROTECTION RULE
                                     * If only morning logs exist and worked >= 2,
                                     * force exactly 4 worked hours.
                                     */
                                    if (
                                        !$isWeekend &&
                                        (!$in2 || !$out2) &&
                                        $worked >= 2
                                    ) {
                                        $worked = 4;
                                    }

                                    $worked = round(max($worked, 0), 2);

                                    /**
                                     * UT COMPUTATION
                                     */
                                    $ut = max(0, 8 - $worked);

                                    /**
                                     * OT COMPUTATION
                                     */
                                    $ot = max(0, $worked - 8);

                                    return [
                                        'ut' => round($ut, 2),
                                        'ot' => round($ot, 2),
                                        'totalWorked' => round($worked, 2),
                                    ];
                                }
                            }
                                        }
    @endphp

    <div class="header-section" style="text-align: center; white-space: nowrap; margin-right: 10%;">
        <p style="font-size: 20px;">DSWD Field Office I</p>
        <p>DAILY TIME RECORD</p>
        <p style="font-weight: normal;  font-size: 10px;">{{$period}}</p>
    </div>
    <div style="text-align: center;">
        <div class="dtr-container">

            <div class="header-section">
                <p style="font-size: 20px;"></p>
                <p></p>
                <p style="font-weight: normal;  font-size: 10px;"></p>
            </div>
            <!-- Header  -->
            <table class="info-table">
                <tr>
                    <td style="width: 100%;">Name:
                        <span style="white-space: nowrap; font-size: 10px; text-decoration: underline;">
                            {{ $fullName }}
                        </span>
                    </td>

                </tr>
                <tr>
                    <td>Position: <span
                            style="white-space: nowrap; font-size: 10px; text-decoration: underline;">{{ $position }}</span>
                    </td>
                </tr>
                <tr>
                    @php
                        if (!function_exists('abbreviate')) {
                            function abbreviate($string, $exclude = ['AND'])
                            {
                                return collect(explode(' ', $string))
                                    ->filter(fn($w) => !in_array(strtoupper($w), $exclude)) // skip excluded words
                                    ->map(fn($w) => strtoupper($w[0])) // take first letter
                                    ->implode('');
                            }
                        }

                        $divisionAbbr = abbreviate($dept_division);
                        $sectionAbbr = abbreviate($dept_section);
                    @endphp
                    <td>
                        Department:
                        <span style="white-space: nowrap; font-size: 10px; text-decoration: underline;">
                            {{ $divisionAbbr }} - {{ $sectionAbbr }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="width: 25%; font-size: 10px;">Regular Time: DEFAULT</td>
                    <td style="width: 20%; white-space: nowrap; font-size: 10px;">Payroll No.: 1 </td>
                </tr>
            </table>
            <!-- Body  -->
            <table class="time-record" style="table-layout: fixed; width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th colspan="3" class="days-column" style="width: 150px; padding-bottom: 10px;">WORKING</th>
                        <th colspan="4" style="width: 140px; padding-bottom: 10px;">A M</th>
                        <th colspan="4" style="width: 140px; padding-bottom: 10px;">P M</th>
                        <th colspan="2" class="ut-ot-td" style="width: 80px; padding-bottom: 10px;">HOURS</th>
                        <th style="background: #fff; border: none;"></th>
                        <th colspan="12" class="ut-ot-td" style="width: 200px; padding-bottom: 10px;">Remarks</th>
                    </tr>
                    <tr>
                        <th colspan="2">Date</th>
                        <th>Days</th>
                        <th colspan="2">In 1</th>
                        <th colspan="2">Out 1</th>
                        <th colspan="2">In 2</th>
                        <th colspan="2">Out 2</th>
                        <th>UT</th>
                        <th>OT</th>
                        <th style="background: #fff; border: none;"></th>
                        <th colspan="12"></th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($allRows as $row)
                        @php
                            $slots = resolveDTRSlots($row->timeLog ?? collect());
                            $remarks = $row->employee_remarks ?: '-';
                            if (!empty($row->timeLog) && count($row->timeLog) > 0) {
                                // Compute UT/OT only if there is at least one time log
                                $computedHours = computeDTRHours($slots);
                                $hours = [
                                    'ut' => ($row->ut == 0.00) ? $computedHours['ut'] : $row->ut,
                                    'ot' => ($row->ot == 0.00) ? $computedHours['ot'] : $row->ot,
                                ];
                            } else {
                                // No time logs → use DB value or 0
                                $hours = [
                                    'ut' => $row->ut ?? 0,
                                    'ot' => $row->ot ?? 0,
                                ];
                            }

                            if (!function_exists('isEdited')) {
                                function isEdited($slot, $timeLogs)
                                {
                                    $slots = resolveDTRSlots($timeLogs);
                                    $slotLog = $slots[$slot] ?? null;

                                    if (!$slotLog)
                                        return false;

                                    foreach ($timeLogs as $log) {
                                        if (
                                            $log->id === $slotLog->id &&
                                            $log->is_in == $slotLog->is_in &&
                                            $log->created_at &&
                                            \Carbon\Carbon::parse($log->date)->format('Y-m-d') !== \Carbon\Carbon::parse($log->created_at)->format('Y-m-d')
                                        ) {
                                            return true;
                                        }
                                    }
                                    return false;
                                }
                            }


                        @endphp
                        <tr>
                            {{-- Date + Day --}}
                            <td colspan="2" style="text-align: center;">
                                {{ \Carbon\Carbon::parse($row->date)->format('j-M') }}
                            </td>
                            <td style="text-align: center;">
                                {{ \Carbon\Carbon::parse($row->date)->format('D') }}
                            </td>

                            {{-- Time slots with edit check --}}
                            <td colspan="2"
                                style="text-align: center; white-space: nowrap; {{ isEdited('in1', $row->timeLog) ? 'color: #b58900; font-style: italic;' : '' }}">
                                {{ $slots['in1'] ? \Carbon\Carbon::parse($slots['in1']->scanned_time)->format('h:i A') : '-' }}
                            </td>

                            <td colspan="2"
                                style="text-align: center; white-space: nowrap; {{ isEdited('out1', $row->timeLog) ? 'color: #b58900; font-style: italic;' : '' }}">
                                {{ $slots['out1'] ? \Carbon\Carbon::parse($slots['out1']->scanned_time)->format('h:i A') : '-' }}
                            </td>

                            <td colspan="2"
                                style="text-align: center; white-space: nowrap; {{ isEdited('in2', $row->timeLog) ? 'color: #b58900; font-style: italic;' : '' }}">
                                {{ $slots['in2'] ? \Carbon\Carbon::parse($slots['in2']->scanned_time)->format('h:i A') : '-' }}
                            </td>

                            <td colspan="2"
                                style="text-align: center; white-space: nowrap; {{ isEdited('out2', $row->timeLog) ? 'color: #b58900; font-style: italic;' : '' }}">
                                {{ $slots['out2'] ? \Carbon\Carbon::parse($slots['out2']->scanned_time)->format('h:i A') : '-' }}
                            </td>


                            {{-- Hours --}}
                            <td style="text-align: center;">{{ $hours['ut'] }}</td>
                            <td style="text-align: center;">{{ $hours['ot'] }}</td>
                            <td style="border-top: none; border-bottom: none;"></td>

                            {{-- Remarks --}}
                            <td colspan="12"
                                style="font-size: 9px; text-align: center; white-space: normal; word-break: break-word; line-height: 1.2; vertical-align: middle;">
                                {{ $remarks }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

            </table>
            <!-- Footer  -->
            <table class="info-table-footer">
                <tr>
                    <td style="width: 20%; white-space: nowrap; " colspan="2" style="text-align: right;">A = <span
                            style="white-space: nowrap; font-size: 10px; text-decoration: underline;"> 26.00</span></td>
                    <td style="text-align: right; width: 10%; white-space: nowrap; ">ROT = <span
                            style="white-space: nowrap; font-size: 10px; text-decoration: underline;"> 0.00</span> </td>
                    <td style="text-align: right;width: 30%; white-space: nowrap; ">LOT = <span
                            style="white-space: nowrap; font-size: 10px; text-decoration: underline;">0.00</span></td>
                </tr>
                <tr>
                    <td style="width: 20%; white-space: nowrap; " colspan="2" style="text-align: right;">U = <span
                            style="white-space: nowrap; font-size: 10px; text-decoration: underline;">0.00</span></td>
                    <td style="text-align: right; width: 10%; white-space: nowrap; ">SOT = <span
                            style="white-space: nowrap; font-size: 10px; text-decoration: underline;">0.00</span></td>
                    <td colspan="2" style="text-align: right;width: 10%; white-space: nowrap; "></td>
                </tr>
            </table>

        </div>

    </div>
    <!-- Certification Table -->
    <table style="width: 57%; margin: 85px auto 5% 17%; border-collapse: collapse; text-align: center;">
        <tbody>
            <!-- Certification Text -->
            <tr>
                <td colspan="2" style="font-size: 10px; font-weight: bold; padding: 5px 10px; width: 50%">
                    I Certify on my honor that the above is a true and correct report
                    of the hours work performed, record of which was daily at
                    the time of arrival and departure from office.
                </td>
            </tr>

            <!-- Employee Signature -->
            <tr>
                <td colspan="2" style="padding-top: 25px;">
                    <div style="border-bottom: 1px solid #000; width: 40%; margin: 0 auto;"></div>
                    <div style="margin-top: 3px; font-size: 10px; font-weight: bold;">Signature</div>
                </td>
            </tr>

            <!-- Verification Text -->
            <tr>
                <td colspan="2" style="padding-top: 15px; font-size: 10px; font-weight: bold;">
                    <div style="border-bottom: 2px dashed #000; width: 40%; margin: 0 auto;"></div>
                    <div style="border-bottom: 2px dashed #000; width: 40%; margin: 1px auto 0;"></div>
                    <p style="margin: 10px 0 0;">VERIFIED as to the prescribed office hours</p>
                </td>
            </tr>

            <!-- In-Charge Signature -->
            <tr>
                <td colspan="2" style="padding-top: 25px;">
                    <div style="border-bottom: 1px solid #000; width: 40%; margin: 0 auto;"></div>
                    <div style="margin-top: 3px; font-size: 10px; font-weight: bold;">In Charge</div>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>