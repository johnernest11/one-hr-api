<?php

namespace App\Services\CompensatoryReport;

use App\Enums\PaginationType;
use App\Models\CompensatoryReport;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CompensatoryReportService implements CompensatoryReportManager
{
    use CanBuildPagination;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    /** {@inheritDoc} */
    public function all(): LengthAwarePaginator
    {
        /** @var Builder $compensatoryReports */
        $query = CompensatoryReport::filtered();

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /** {@inheritDoc} */
    public function read(CompensatoryReport $compensatoryReport): CompensatoryReport
    {
        return $compensatoryReport->load('rows');
    }
}
