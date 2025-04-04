<?php

namespace App\Http\Controllers;

use App\Enums\PaginationType;
use App\Http\Requests\ItemRequest;
use App\Models\Item;
use App\Services\Item\ItemService;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends ApiController
{
    private ItemService $itemService;

    public function __construct(ItemService $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $items = $this->itemService->all();
        $formatted = PaginationHelper::formatPagination($items);

        return $this->success($formatted, Response::HTTP_OK);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ItemRequest $request): JsonResponse
    {

        $item = $this->itemService->create($request->validated());

        return $this->success(['data' => $item], Response::HTTP_CREATED);

    }

    /**
     * Display the specified resource.
     */
    public function show(Item $item): JsonResponse
    {
        $item = $this->itemService->read($item);

        return $this->success(['data' => $item], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ItemRequest $request, Item $item): JsonResponse
    {

        $updatedItem = $this->itemService->update($item, $request->validated());

        return $this->success(['data' => $updatedItem], Response::HTTP_OK);

    }

    /**
     * Search for a resource in storage.
     */
    public function search(ItemRequest $request): JsonResponse
    {
        $items = $this->itemService->search($request->validated('query'), PaginationType::LENGTH_AWARE);
        $formatted = PaginationHelper::formatPagination($items);

        return $this->success($formatted, Response::HTTP_OK);

    }
}
