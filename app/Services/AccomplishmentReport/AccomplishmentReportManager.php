<?php

namespace App\Services\AccomplishmentReport;

use App\Models\AccomplishmentReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AccomplishmentReportManager
{
    /**
     * Fetch all Accomplishment Reports
     */
    public function all(): LengthAwarePaginator;

    /**
     * Create an Accomplishment Report
     */
    public function create(User $user, array $arInfo): AccomplishmentReport;

    /**
     * Generate an Accomplishment Report
     */
    public function generate(AccomplishmentReport $accomplishmentReport): array;

    /**
     * Fetch a single Accomplishment Report
     */
    public function read(AccomplishmentReport $accomplishmentReport): AccomplishmentReport;

    /**
     * Update an Accomplishment Report
     */
    public function update(AccomplishmentReport $accomplishmentReport, array $newReportInfo): AccomplishmentReport;
}
