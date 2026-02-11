<?php

namespace App\Traits\Services;

use App\Enums\AcademicLevel;
use App\Enums\Citizenship;
use App\Enums\CitizenshipAcquisition;
use App\Enums\CivilStatus;
use App\Enums\FamilyMemberCategory;
use App\Enums\SexualCategory;
use App\Models\Address\Barangay;
use App\Models\Address\City;
use App\Models\Address\Province;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\Libraries\Country;
use Carbon\Carbon;
use FPDF;

class PdsPdfBuilder
{
    protected ?FPDF $pdf = null; // make nullable

    public function __construct()
    {
        $this->initPdf();
    }

    protected function initPdf(): void
    {
        if ($this->pdf === null) {
            $this->pdf = new FPDF('P', 'pt', 'Legal');
            $this->pdf->SetFont('Arial', '', 11);
            $this->pdf->SetTextColor(0, 0, 0);
        }
    }

    public function loadTemplate(string $path): self
    {
        $this->initPdf();
        if (! file_exists($path)) {
            throw new \Exception("Template not found at: {$path}");
        }

        $this->pdf->AddPage();
        $this->pdf->Image($path, 0, 0, 612, 1008);

        return $this;
    }

    protected function setText(float $x, float $y, float $width, string $text, string $align = ''): void
    {
        $this->initPdf();
        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($width, 19.5, strtoupper($text ?? ''), 0, 0, $align);
    }

    public function renderPersonalInfo(IndividualBasicDetail $ind): self
    {
        $this->setText(131, 133, 440, $ind->last_name);
        $this->setText(131, 153, 310, $ind->first_name);
        $this->setText(443, 155, 100, $ind->ext_name?->value ?? '');
        $this->setText(131, 171, 440, $ind->middle_name);
        $this->setText(131, 193, 130, $ind->birthday?->format('m/d/Y'));

        // Citizenship checkboxes
        $this->pdf->SetFont('ZapfDingbats', '', 7);

        $citizenship = $ind->citizenship;
        if ($citizenship === Citizenship::FILIPINO) {
            $this->pdf->SetXY(382.5, 200);
            $this->pdf->Cell(10, 10, '4'); // check
        } elseif ($citizenship === Citizenship::DUAL_CITIZENSHIP) {
            $this->pdf->SetXY(432.5, 200);
            $this->pdf->Cell(10, 10, '4');
        }

        // Citizenship acquisition
        $citizenship_acquisition = $ind->citizenship_acquisition;
        if ($citizenship_acquisition === CitizenshipAcquisition::BIRTH) {
            $this->pdf->SetXY(445.5, 213);
            $this->pdf->Cell(10, 10, '4');
        } elseif ($citizenship_acquisition === CitizenshipAcquisition::NATURALIZATION) {
            $this->pdf->SetXY(486.5, 213);
            $this->pdf->Cell(10, 10, txt: '4');
        }

        // Sex

        $sex = $ind->sex;

        if ($sex === SexualCategory::MALE) {
            // Mark male checkbox
            $this->pdf->SetXY(135, 250);
            $this->pdf->Cell(10, 8, '4');
        } elseif ($sex === SexualCategory::FEMALE) {
            // Mark female checkbox
            $this->pdf->SetXY(210.5, 250);
            $this->pdf->Cell(10, 8, '4');
        }
        // Civil status
        $civilStatusPositions = [
            CivilStatus::SINGLE->value => [135, 268],
            CivilStatus::MARRIED->value => [210.5, 268],
            CivilStatus::WIDOWED->value => [135, 280],
            CivilStatus::SEPARATED->value => [210.5, 280],
        ];

        // Convert enum object to string safely
        $status = $ind->civil_status?->value ?? '';

        if (isset($civilStatusPositions[$status])) {
            [$x, $y] = $civilStatusPositions[$status];
            $this->pdf->SetXY($x, $y);
            $this->pdf->Cell(10, 8, '4');
        }
        $this->pdf->SetFont('Arial', '', 7);
        $this->setText(378, 244, 165, Country::find($ind->country_id)?->common_name ?? '', 'C');

        return $this;
    }

    public function renderAddress(IndividualBasicDetail $ind): self
    {
        $this->pdf->SetFont('Arial', '', 7);
        // Residential
        $this->setText(338, 265, 118, $ind->individualAddress->residential_house_block_lot_no ?? '', 'C');
        $this->setText(456, 265, 118, $ind->individualAddress->residential_street ?? '', 'C');
        $this->setText(338, 285, 118, $ind->individualAddress->residential_subdivision_village ?? '', 'C');
        $this->setText(456, 285, 118, Barangay::find($ind->individualAddress->residential_brgy_id)?->name ?? '', 'C');
        $this->setText(338, 306, 118, City::find($ind->individualAddress->residential_citymun_id)?->name ?? '', 'C');
        $this->setText(456, 306, 118, Province::find($ind->individualAddress->residential_province_id)?->name ?? '', 'C');
        $this->setText(338, 326, 236, $ind->individualAddress->residential_zip_code ?? '', 'C');

        // Permanent
        $this->setText(338, 346, 118, $ind->individualAddress->permanent_house_block_lot_no ?? '', 'C');
        $this->setText(456, 346, 118, $ind->individualAddress->permanent_street ?? '', 'C');
        $this->setText(338, 366, 118, $ind->individualAddress->permanent_subdivision_village ?? '', 'C');
        $this->setText(456, 366, 118, Barangay::find($ind->individualAddress->permanent_brgy_id)?->name ?? '', 'C');
        $this->setText(338, 389, 118, City::find($ind->individualAddress->permanent_citymun_id)?->name ?? '', 'C');
        $this->setText(456, 389, 118, Province::find($ind->individualAddress->permanent_province_id)?->name ?? '', 'C');
        $this->setText(338, 410, 236, $ind->individualAddress->permanent_zip_code ?? '', 'C');

        return $this;
    }

    public function renderIds(IndividualBasicDetail $ind): self
    {
        $this->pdf->SetFont('Arial', '', 10);
        $this->setText(131, 366, 128, $ind->gsis_no ?? '');
        $this->setText(131, 389, 128, $ind->pag_ibig_no ?? '');
        $this->setText(131, 410, 128, $ind->philhealth_no ?? '');
        $this->setText(131, 431.5, 128, $ind->sss_no ?? '');
        $this->setText(131, 451.5, 128, $ind->tin ?? '');
        $this->setText(131, 474, 128, $ind->employee->agency_employee_no ?? '');

        return $this;
    }

    public function renderContact(IndividualBasicDetail $ind): self
    {
        $this->setText(338, 431.5, 236, $ind->individualContactInfo->tel_no);
        $this->setText(338, 451.5, 236, $ind->individualContactInfo->mobile_no);
        $this->setText(338, 474, 236, $ind->individualContactInfo->email_address ?? '');

        return $this;
    }

    public function renderPhysicalInfo(IndividualBasicDetail $ind): self
    {
        // Height, Weight, Blood Type
        $this->setText(131, 306, 128, $ind->height);
        $this->setText(131, 326, 128, $ind->weight);
        $this->setText(131, 346, 128, $ind->blood_type?->value ?? '');

        return $this;
    }

    public function renderFamilyBackground(IndividualBasicDetail $ind): self
    {

        $spouse = $ind->individualFamily->firstWhere('class', FamilyMemberCategory::SPOUSE);

        if ($spouse) {
            $this->setText(131, 508, 207, $spouse->last_name ?? 'N/A');
            $this->setText(131, 526, 128, $spouse->first_name ?? 'N/A');
            $this->setText(260, 526, 78, $spouse->ext_name?->value ?? 'N/A');
            $this->setText(131, 544.5, 207, $spouse->middle_name ?? 'N/A');
            $this->setText(131, 562, 207, $spouse->occupation ?? 'N/A');
            $this->setText(131, 580, 207, $spouse->employers_business_name ?? 'N/A');
            $this->setText(131, 598, 207, $spouse->business_address ?? 'N/A');
            $this->setText(131, 615.5, 207, $spouse->telephone_no ?? 'N/A');
        } else {
            // Spouse not found — fill all fields with 'N/A'
            $this->setText(131, 508, 207, 'N/A');
            $this->setText(131, 526, 128, 'N/A');
            $this->setText(260, 526, 78, 'N/A');
            $this->setText(131, 544.5, 207, 'N/A');
            $this->setText(131, 562, 207, 'N/A');
            $this->setText(131, 580, 207, 'N/A');
            $this->setText(131, 598, 207, 'N/A');
            $this->setText(131, 615.5, 207, 'N/A');
        }

        $father = $ind->individualFamily->firstWhere('class', FamilyMemberCategory::FATHER);

        if ($father) {
            $this->setText(131, 634, 207, $father->last_name ?? '');
            $this->setText(131, 652, 128, $father->first_name ?? '');
            $this->setText(260, 652, 78, $father->ext_name?->value ?? '');
            $this->setText(131, 670, 207, $father->middle_name ?? '');
        }

        $mother = $ind->individualFamily->firstWhere('class', FamilyMemberCategory::MOTHER);

        if ($mother) {
            $this->setText(131, 705, 207, $mother->last_name ?? '');
            $this->setText(131, 723, 207, $mother->first_name ?? '');
            $this->setText(131, 741, 207, $mother->middle_name ?? '');
        }

        $childrenList = $ind->individualFamily->where('class', FamilyMemberCategory::CHILDREN);

        if ($childrenList->isNotEmpty()) {
            $yPosition = 526; // starting vertical position
            foreach ($childrenList as $child) {
                $fullName = trim(
                    ($child->last_name ?? '').', '.
                    ($child->first_name ?? '').' '.
                    ($child->middle_name ?? '')
                );

                $maxChars = 25;
                if (strlen($fullName) > $maxChars) {
                    $fullName = substr($fullName, 0, $maxChars - 3).'...';
                }

                $this->setText(338, $yPosition, 152, $fullName ?: 'N/A');
                $dob = $child->date_of_birth
                    ? Carbon::parse($child->date_of_birth)->format('d/m/Y')
                    : 'N/A';
                $this->setText(490, $yPosition, 84, $dob);

                $yPosition += 18; // move down for the next child
            }
        } else {
            $this->setText(338, 526, 152, 'N/A');
            $this->setText(490, 526, 84, 'N/A');
        }

        return $this;
    }

    public function renderEducationalBackground(IndividualBasicDetail $ind): self
    {

        $elementary = $ind->individualEducationalBackground()->firstWhere('level', AcademicLevel::ELEMENTARY);

        if ($elementary) {
            $this->setText(131, 820, 128, $elementary->schools_name ?? 'N/A');
            $this->setText(259, 820, 118, $elementary->education_description ?? 'N/A');
            $this->setText(377, 820, 32, $elementary->period_of_attendance_from ?? 'N/A');
            $this->setText(409, 820, 32, $elementary->period_of_attendance_to ?? 'N/A');
            $this->setText(441, 820, 48, $elementary->highest_grade_level ?? 'N/A');
            $this->setText(489, 820, 39, $elementary->units_earned ?? 'N/A');
            $this->setText(528, 820, 46, $elementary->year_graduated ?? 'N/A');
        }

        $high_School = $ind->individualEducationalBackground()->firstWhere('level', AcademicLevel::SECONDARY);

        if ($high_School) {
            $this->setText(131, 845, 128, $high_School->schools_name ?? 'N/A');
            $this->setText(259, 845, 118, $high_School->education_description ?? 'N/A');
            $this->setText(377, 845, 32, $high_School->period_of_attendance_from ?? 'N/A');
            $this->setText(409, 845, 32, $high_School->period_of_attendance_to ?? 'N/A');
            $this->setText(441, 845, 48, $high_School->highest_grade_level ?? 'N/A');
            $this->setText(489, 845, 39, $high_School->units_earned ?? 'N/A');
            $this->setText(528, 845, 46, $high_School->year_graduated ?? 'N/A');
        }

        $vocational = $ind->individualEducationalBackground()->firstWhere('level', AcademicLevel::VOCATIONAL);

        if ($vocational) {
            $this->setText(131, 870, 128, $vocational->schools_name ?? 'N/A');
            $this->setText(259, 870, 118, $vocational->education_description ?? 'N/A');
            $this->setText(377, 870, 32, $vocational->period_of_attendance_from ?? 'N/A');
            $this->setText(409, 870, 32, $vocational->period_of_attendance_to ?? 'N/A');
            $this->setText(441, 870, 48, $vocational->highest_grade_level ?? 'N/A');
            $this->setText(489, 870, 39, $vocational->units_earned ?? 'N/A');
            $this->setText(528, 870, 46, $vocational->year_graduated ?? 'N/A');
        }

        $college = $ind->individualEducationalBackground()->firstWhere('level', AcademicLevel::COLLEGE);

        if ($college) {
            $y = 893;

            // Cells to render with adjustable font
            $cells = [
                ['x' => 131, 'width' => 128, 'text' => $college->schools_name ?? 'N/A'],
                ['x' => 259, 'width' => 118, 'text' => $college->education_description ?? 'N/A'],
            ];

            foreach ($cells as $cell) {
                $fontSize = 12; // default font size
                $textLength = strlen($cell['text']);

                // Shrink font if text is too long for the cell
                if ($textLength > $cell['width'] / 5) { // rough estimate
                    $fontSize = max(6, $cell['width'] / ($textLength / 1.5)); // minimum font size 6
                }

                $this->setText($cell['x'], $y, $cell['width'], $cell['text'], $fontSize);
            }

            // Remaining fixed cells (years, grades, units, etc.)
            $this->setText(377, $y, 32, $college->period_of_attendance_from ?? 'N/A');
            $this->setText(409, $y, 32, $college->period_of_attendance_to ?? 'N/A');
            $this->setText(441, $y, 48, $college->highest_grade_level ?? 'N/A');
            $this->setText(489, $y, 39, $college->units_earned ?? 'N/A');
            $this->setText(528, $y, 46, $college->year_graduated ?? 'N/A');
        }

        $graduate = $ind->individualEducationalBackground()->firstWhere('level', AcademicLevel::GRADUATE);

        if ($graduate) {
            $this->setText(131, 918, 128, $graduate->schools_name ?? 'N/A');
            $this->setText(259, 918, 118, $graduate->education_description ?? 'N/A');
            $this->setText(377, 918, 32, $graduate->period_of_attendance_from ?? 'N/A');
            $this->setText(409, 918, 32, $graduate->period_of_attendance_to ?? 'N/A');
            $this->setText(441, 918, 48, $graduate->highest_grade_level ?? 'N/A');
            $this->setText(489, 918, 39, $graduate->units_earned ?? 'N/A');
            $this->setText(528, 918, 46, $graduate->year_graduated ?? 'N/A');
        }

        return $this;
    }

    public function output(): string
    {
        $this->initPdf();

        return $this->pdf->Output('S');
    }
}
