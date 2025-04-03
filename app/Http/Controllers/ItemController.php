<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use App\Models\Item;
use App\Services\Item\ItemService;
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
    public function index()
    {
        $items = $this->itemService->all();
        $formatted = PaginationHelper::formatPagination($items);

        return $this->success($formatted, Response::HTTP_OK);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ItemRequest $request)
    {

        $item = $this->itemService->create($request->validated());

        return $this->success(['data' => $item], Response::HTTP_CREATED);

    }

    /**
     * Display the specified resource.
     */
    public function show(Item $item)
    {
        $item = $this->itemService->read($item);

        return $this->success(['data' => $item], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ItemRequest $request, Item $item)
    {

        $updatedItem = $this->itemService->update($item, $request->validated());

        return $this->success(['data' => $updatedItem], Response::HTTP_OK);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
