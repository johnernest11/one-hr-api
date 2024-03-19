<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

class PermissionController extends ApiController
{
    /** Returns all permissions */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();
        $guard = 'api_key';

        if (! $request->get('type') || $request->get('type') === 'users') {
            $guard = 'token';
        }

        $permissions = $query->where('guard_name', $guard)->get();

        return $this->success(['data' => $permissions], Response::HTTP_OK);
    }
}
