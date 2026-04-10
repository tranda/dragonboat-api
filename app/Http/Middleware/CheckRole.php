<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole {
    public function handle(Request $request, Closure $next, string ...$roles): mixed {
        if (!Auth::check()) return response()->json(['error' => 'Unauthenticated'], 401);
        $userRole = Auth::user()->role?->name;
        if (!in_array($userRole, $roles)) return response()->json(['error' => 'Unauthorized'], 403);
        return $next($request);
    }
}
