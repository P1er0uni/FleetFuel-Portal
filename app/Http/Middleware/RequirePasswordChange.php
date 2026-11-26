<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After an admin sets a temporary password, the user must change it before
 * using any other page (Fase 4, D7). The password-change screen itself and the
 * logout route are allowed through so the user can complete the change or leave.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && $user->must_change_password
            && ! $request->routeIs('password.change.edit', 'password.change.update', 'logout')) {
            return redirect()->route('password.change.edit');
        }

        return $next($request);
    }
}
