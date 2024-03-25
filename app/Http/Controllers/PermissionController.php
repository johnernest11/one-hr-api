<?php

namespace App\Http\Controllers;

use App\Http\Requests\PermissionRequest;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

class PermissionController extends ApiController
{
    /** Returns all permissions */
    public function index(PermissionRequest $request): JsonResponse
    {
        $query = Permission::query();
        $guard = 'api_key';

        if (! $request->get('type') || $request->get('type') === 'users') {
            $guard = 'token';
        }

        if ($request->get('type') === 'all') {
            $permissions = $query->get();
        } else {
            $permissions = $query->where('guard_name', $guard)->get();
        }

        return $this->success(['data' => $permissions], Response::HTTP_OK);
    }
}
