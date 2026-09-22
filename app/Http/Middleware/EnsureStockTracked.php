<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stock Management and Purchases only make sense when the store tracks
 * quantities. A store that chose the "simple" mode has neither concept, so
 * these routes are blocked instead of showing empty/meaningless screens.
 */
class EnsureStockTracked
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!store_tracks_stock()) {
            return redirect()->route('home')->with('error', __('This store does not track stock quantity.'));
        }

        return $next($request);
    }
}
