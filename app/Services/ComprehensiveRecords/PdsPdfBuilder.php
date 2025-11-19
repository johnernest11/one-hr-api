<?php

namespace App\Services\ComprehensiveRecords;

use App\Models\Address\Barangay;
use App\Models\Address\City;
use App\Models\Address\Province;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
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
            $this->pdf->AddPage();
            $this->pdf->SetFont('Arial', '', 10);
            $this->pdf->SetTextColor(0, 0, 0);
        }
    }

    public function loadTemplate(string $path): self
    {
        $this->initPdf();
        if (! file_exists($path)) {
            throw new \Exception("Template not found at: {$path}");
        }
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
        $this->setText(443, 159, 100, $ind->middle_name);
        $this->setText(131, 171, 440, $ind->middle_name);
        $this->setText(131, 193, 130, $ind->birthday?->format('m/d/Y'));

        // Citizenship checkboxes
        // $this->pdf->SetFont('ZapfDingbats', '', 7);
        // if ($ind->citizenship?->value === 'Filipino') {
        //     $this->pdf->Cell(122.5, 10, "4"); // check
        // } elseif ($ind->citizenship?->value === 'Dual Citizenship') {
        //     $this->pdf->Cell(171.5, 10, "4");
        // }

        // // Citizenship acquisition
        // if ($ind->citizenship_acquisition?->value === 'By Birth') {
        //     $this->pdf->Cell(417.5, 10, "4");
        // } elseif ($ind->citizenship_acquisition?->value === 'By Naturalization') {
        //     $this->pdf->Cell(459, 10, "4");
        // }

        // Sex
        $this->pdf->SetFont('ZapfDingbats', '', 7);
        if ($ind->sex?->value === 'male') {
            $this->pdf->SetXY(135, 250);
            $this->pdf->Cell(10, 8, '4');
        } elseif ($ind->sex?->value === 'female') {
            $this->pdf->SetXY(210.5, 250);
            $this->pdf->Cell(10, 8, '4');
        }

        // Civil status
        $civilStatusPositions = [
            'Single' => [135, 268],
            'Married' => [210.5, 268],
            'Widowed' => [135, 280],
            'Separated' => [210.5, 280],
            '' => [135, 293],
        ];
        $status = $ind->civil_status?->value ?? '';
        if (isset($civilStatusPositions[$status])) {
            [$x, $y] = $civilStatusPositions[$status];
            $this->pdf->SetXY($x, $y);
            $this->pdf->Cell(10, 8, '4');
        }

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
        $this->setText(131, 366, 128, $ind->gsis_no);
        $this->setText(131, 389, 128, $ind->pag_ibig_no);
        $this->setText(131, 410, 128, $ind->philhealth_no);
        $this->setText(131, 431.5, 128, $ind->sss_no);
        $this->setText(131, 451.5, 128, $ind->tin);
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
