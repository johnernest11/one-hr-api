<?php

namespace App\Traits\Services;

use App\Enums\Citizenship;
use App\Enums\CitizenshipAcquisition;
use App\Enums\CivilStatus;
use App\Enums\SexualCategory;
use App\Models\Address\Barangay;
use App\Models\Address\City;
use App\Models\Address\Province;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\Libraries\Country;
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
        $this->pdf->Cell($width, 20, strtoupper($text ?? ''), 0, 0, $align);
    }

    public function renderPersonalInfo(IndividualBasicDetail $ind): self
    {
        $this->setText(131, 133, 440, $ind->last_name);
        $this->setText(131, 153, 310, $ind->first_name);
        $this->setText(443, 155, 100, $ind->middle_name);
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

    public function output(): string
    {
        $this->initPdf();

        return $this->pdf->Output('S');
    }
}
