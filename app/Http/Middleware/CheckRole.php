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
        $roles = array_values(array_filter(array_map(static fn (string $r) => trim($r), $roles)));

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
                'code' => 401,
            ], 401);
        }

        if (empty($roles)) {
            return $next($request);
        }

        $activeRole = method_exists($user, 'resolveActiveRole')
            ? $user->resolveActiveRole($request->header('X-Active-Role'))
            : $user->role;
        $user->setAttribute('role', $activeRole);

        if (! in_array($activeRole, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Role tidak diizinkan.',
                'data' => [
                    'required_roles' => $roles,
                    'current_role' => $activeRole,
                    'available_roles' => method_exists($user, 'availableRoles') ? $user->availableRoles() : [$user->role],
                ],
                'code' => 403,
            ], 403);
        }

        return $next($request);
    }
}
