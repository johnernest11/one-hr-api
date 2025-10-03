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
            width: 50%;
            /* Mimics the width of one copy on the page */
            margin: -25;
            float: left;
            margin-top: 10px;
            /* Aligns to the left */
        }

        .dtr-container-right {
            border: 0px solid #000;
            padding: 0px;
            width: 50%;
            /* Mimics the width of one copy on the page */
            margin: 10;
            float: right;
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
            width: 100%;
            margin-bottom: 5px;
        }

        .info-table-footer {
            margin: 16;
            margin-top: 0px;
            margin-bottom: 6px;
            width: 100%;
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

        $slots = ['in1' => null, 'out1' => null, 'in2' => null, 'out2' => null];

        if ($timeLogs->isEmpty()) {
            return $slots;
        }

        $sorted = $timeLogs->sortBy(function ($log) {
            return strtotime($log->date . ' ' . $log->scanned_time);
        })->values();

        $getHour = fn($log) => (int) date('H', strtotime($log->scanned_time));

        // IN1: earliest 6–12
        $slots['in1'] = $sorted->first(fn($log) => ($h = $getHour($log)) >= 6 && $h < 12);

        // OUT1: first 12–13
        $slots['out1'] = $sorted->first(fn($log) => ($h = $getHour($log)) >= 12 && $h < 13);

        // IN2: first log between 12–14 and 15 mins after OUT1
        if ($slots['out1']) {
            $out1Time = strtotime($slots['out1']->scanned_time);
            $slots['in2'] = $sorted->first(function ($log) use ($out1Time) {
                $time = strtotime($log->scanned_time);
                $h = (int) date('H', $time);
                return $h >= 12 && $h < 14 && $time >= $out1Time + (15 * 60);
            });
        }

        // OUT2: last ≥ 14h
        $slots['out2'] = $sorted->last(fn($log) => (int) date('H', strtotime($log->scanned_time)) >= 14);

        return $slots;
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
                <td>Department: <span
                        style="white-space: nowrap; font-size: 10px; text-decoration: underline;">{{ $dept_division }}-{{ $dept_section }}</span>
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
                </tr>

                <tr>
                    <th colspan="2" class="dates-column" style="width: 100px;">Date</th>
                    <th class="days-column" style="width: 50px;">Days</th>
                    <th colspan="2" class="am-pm-data" style="text-align: center; white-space: nowrap;">In 1</th>
                    <th colspan="2" class="am-pm-data" style="text-align: center; white-space: nowrap;">Out 1</th>
                    <th colspan="2" class="am-pm-data" style="text-align: center; white-space: nowrap;">In 2</th>
                    <th colspan="2" class="am-pm-data" style="text-align: center; white-space: nowrap;">Out 2</th>
                    <th class="ut-ot-td" style="text-align: center;">UT</th>
                    <th class="ut-ot-td" style="text-align: center;">OT</th>
                </tr>
            </thead>

            <tbody>
                @foreach($allRows as $row)
                    @php
    $slots = resolveDTRSlots($row->timeLog ?? collect());
                    @endphp
                    <tr>
                        <td colspan="2" style="text-align: center;">
                            {{ \Carbon\Carbon::parse($row->date)->format('j-M') }}
                        </td>
                        <td style="text-align: center;">
                            {{ \Carbon\Carbon::parse($row->date)->format('D') }}
                        </td>
                        <td colspan="2" style="text-align: center; white-space: nowrap;">
                            {{ $slots['in1'] ? \Carbon\Carbon::parse($slots['in1']->scanned_time)->format('h:i A') : '-' }}
                        </td>
                        <td colspan="2" style="text-align: center; white-space: nowrap;">
                            {{ $slots['out1'] ? \Carbon\Carbon::parse($slots['out1']->scanned_time)->format('h:i A') : '-' }}
                        </td>
                        <td colspan="2" style="text-align: center; white-space: nowrap;">
                            {{ $slots['in2'] ? \Carbon\Carbon::parse($slots['in2']->scanned_time)->format('h:i A') : '-' }}
                        </td>
                        <td colspan="2" style="text-align: center; white-space: nowrap;">
                            {{ $slots['out2'] ? \Carbon\Carbon::parse($slots['out2']->scanned_time)->format('h:i A') : '-' }}
                        </td>
                        <td style="text-align: center;">{{ $row->ut }}</td>
                        <td style="text-align: center;">{{ $row->ot }}</td>
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

    <div class="dtr-container-right">
        <!-- Body  -->
        <table class="time-record" style="table-layout: fixed; width: 90%; border-collapse: collapse; margin-top: 30%">
            <thead>
                <tr>
                    <th colspan="8" style="width: 140px; padding-bottom: 10px;">&nbsp;</th>
                </tr>

                <tr>
                    <th colspan="8" class="dates-column" style="width: 100px;">REMARKS</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allRows as $row)
                    <tr style="border: none;">

                        <td colspan="8" style="text-align: center; border-bottom: 1px solid #000;">
                            {{ $row->employee_remarks ?: '-' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
    <div style="text-align: center; margin-top: 100px; margin-bottom: 10%; ">
        <div  style="display: inline-block; text-align: center; width: 70%;">
           

            <div>
                <div  style="width: 80%; margin: 0 auto; text-align: center; ">
                    <div class="label" style="margin-top: 3px; font-size: 10px; font-weight: bold; font-color: white;">
                       -
                    </div>
                </div>
                <div style="width: 80%; margin: 0 auto; text-align: center; ">
                    <div class="label" style="margin-top: 3px; font-size: 10px; font-weight: bold; ">
                        I Certify on my honor that the above is a true and correct report
                        of the hours work performed, record of which was daily at
                        the time of arrival and departure from office.
                    </div>
                </div>
            </div>
            <div class="signature-block" style="margin-top: 25px;">
                <div class="sig-item" style="width: 80%; margin: 0 auto; text-align: center;">
                    <div style="border-bottom: 1px solid #000; width: 100%;"></div>
                    <div class="label" style="margin-top: 3px; font-size: 10px; font-weight: bold;">
                        Signature
                    </div>
                </div>
            </div>
    
            <div class="verification-block" style="margin-top: 25px; text-align: center;">
                <div style="border-bottom: 2px dashed #000; width: 80%; margin: 0 auto;"></div>
                <div style="border-bottom: 2px dashed #000; width: 80%; margin: 0.5px auto 0;"></div>
                <p style="margin: 10px 0 0; font-size: 10px; font-weight: bold;">
                    VERIFIED as to the prescribed office hours
                </p>
    
                <div class="signature-block" style="margin-top: 30px;">
                    <div class="sig-item" style="width: 80%; margin: 0 auto; text-align: center;">
                        <div style="border-bottom: 1px solid #000; width: 100%;"></div>
                        <div class="label" style="margin-top: 3px; font-size: 10px; font-weight: bold;">
                            In Charge
                        </div>
                    </div>
                </div>
            </div>
    
        </div>
    </div>


</body>

</html>