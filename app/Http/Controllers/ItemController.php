<?php

namespace App\Http\Controllers;

use App\Enums\PaginationType;
use App\Http\Requests\ItemRequest;
use App\Models\Item;
use App\Services\Item\ItemManager;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class ItemController extends ApiController
{
    private ItemManager $itemService;

    public function __construct(ItemManager $itemService)
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
        $this->authorize('viewItem', [$item]);
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
        $validated = $request->validated();

        $query = $validated['query'] ?? null;
        $limit = $validated['limit'] ?? 5;
        $page = $validated['page'] ?? null;

        $items = $this->itemService->search($query, PaginationType::LENGTH_AWARE, $limit, $page);
        $formatted = PaginationHelper::formatPagination($items);

        return $this->success($formatted, Response::HTTP_OK);

    }
}
