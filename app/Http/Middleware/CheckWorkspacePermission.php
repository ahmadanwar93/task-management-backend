<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWorkspacePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $slug = $request->route('slug');
        $workspace = $request->user()->workspaces()
            ->where('slug', $slug)
            ->with(['users', 'owner'])
            ->first();

        if (!$workspace) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not found',
            ], 404);
        }

        if (!$request->user()->hasPermissionInWorkspace($permission, $workspace)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action',
            ], 403);
        }
        $request->merge(['workspace' => $workspace]);

        return $next($request);
    }
}
