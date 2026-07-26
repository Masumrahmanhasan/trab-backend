<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTeamContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $teamId = null;

        // Try to get team_id from various sources
        // Priority: route parameter > header > query parameter
        if ($request->route('store_id')) {
            $teamId = $request->route('store_id');
        } elseif ($request->header('X-Team-Id')) {
            $teamId = $request->header('X-Team-Id');
        } elseif ($request->query('team_id')) {
            $teamId = $request->query('team_id');
        }

        // Set team_id on authenticated user if found
        if ($teamId && $request->user()) {
            $request->user()->withTeamId((int) $teamId);
        }

        return $next($request);
    }
}
