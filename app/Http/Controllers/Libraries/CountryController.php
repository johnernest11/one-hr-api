<?php

namespace App\Http\Controllers\Libraries;

use App\Enums\PaginationType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\CountryRequest;
use App\Models\Libraries\Country;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class CountryController extends ApiController
{
    use CanBuildPagination;

    /**
     * Retrieve all countries
     */
    public function fetch(CountryRequest $request): JsonResponse
    {
        $query = Country::query()->orderBy('common_name');
        $countries = $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

        $countries_formatted = PaginationHelper::formatPagination($countries);

        return $this->success($countries_formatted, Response::HTTP_OK);

    }

    /**
     * Search for a country via common name or official name
     */
    public function search(CountryRequest $request): JsonResponse
    {
        $q = $request->validated()['query'];
        $limit = $request->validated('limit', 9);

        $countries = Country::where('common_name', 'like', "%$q%")->orWhere('official_name', 'like', "%$q%")->paginate($limit)->toArray();

        $countries_formatted = PaginationHelper::formatLengthAwarePagination($countries);

        return $this->success($countries_formatted, Response::HTTP_OK);
    }
}
