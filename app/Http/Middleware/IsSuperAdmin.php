<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Authentication required',
                'status' => 401,
                'data' => [],
            ], 401);
        }

        if (! $user->hasRole('super-admin')) {
            return response()->json([
                'message' => 'Super admin access required',
                'status' => 403,
                'data' => [
                    'required_role' => 'super-admin',
                    'user_roles' => $user->roles->pluck('key')->toArray(),
                ],
            ], 403);
        }

        return $next($request);
    }
}
