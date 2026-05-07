<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
                'code' => 401,
            ], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Role tidak diizinkan.',
                'data' => [
                    'required_roles' => $roles,
                    'current_role' => $user->role,
                ],
                'code' => 403,
            ], 403);
        }

        return $next($request);
    }
}
