<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends ApiController
{
    /**
     * Get all roles
     */
    public function index(): JsonResponse
    {
        $roles = Role::all();

        return $this->success(['data' => $roles], Response::HTTP_OK);
    }
}
