<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\Users\AssignRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserRoleAssignmentController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AssignRoleRequest $request, User $user): JsonResponse
    {
        $role = (string) $request->validated('role');

        $user->assignRole($role);

        return response()->json([
            'message' => 'Rol asignado correctamente.',
            'role' => $role,
            'user_id' => $user->getKey(),
        ]);
    }
}
