<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\CountryRequest;
use App\Models\Libraries\Country;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class CountryController extends ApiController
{
    /**
     * Retrieve all countries
     */
    public function fetch(CountryRequest $request): JsonResponse
    {
        $countries = Country::orderBy('common_name')->paginate(9)->toArray();

        $countries_formatted = PaginationHelper::formatLengthAwarePagination($countries);

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
