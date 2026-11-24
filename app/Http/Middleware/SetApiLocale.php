<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The web screens are Spanish (APP_LOCALE=es), but the JSON API keeps its
 * documented English contract (docs/05-API-CONTRACT.md, the Postman
 * collection and the API tests): framework validation messages under
 * api/* stay English. Custom messages are not locale-aware, so the API
 * request classes override messages() with the English wording.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            App::setLocale('en');
        }

        return $next($request);
    }
}
