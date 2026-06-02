<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\ApiController;
use App\Models\Libraries\LocatorActivity;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LocatorActivityController extends ApiController
{
    use CanBuildPagination;

    /**
     * Retrieve all locator activities
     */
    public function fetch(): JsonResponse
    {
        // Fetch only top-level roots ('0', '1') and automatically nest all children down the chain
        $activities = LocatorActivity::whereNull('parent_id')
            ->with('children')
            ->orderBy('key')
            ->get();

        return $this->success(['data' => $activities], Response::HTTP_OK);
    }
}
