<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the superadmin out of the store screens (POS, stock...), keeps store
 * users out of the superadmin screens, blocks suspended stores and applies
 * the store's own settings (name, logo, currency, tax...).
 */
class EnsureStoreAccess
{
    /** Routes the superadmin may open besides superadmin.* */
    private const SUPERADMIN_SHARED = ['home', 'profile.*', 'lang.switch'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            if (!$request->routeIs('superadmin.*', ...self::SUPERADMIN_SHARED)) {
                return redirect()->route('superadmin.stores.index');
            }

            return $next($request);
        }

        if ($request->routeIs('superadmin.*')) {
            abort(403);
        }

        $store = $user->store;
        if (!$store || !$store->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('This store account is suspended. Please contact the administrator.'),
            ]);
        }

        // Store settings override the platform-wide defaults.
        $storeSettings = Setting::where('store_id', $store->id)->pluck('value', 'key')->all();
        config(['settings' => array_merge(config('settings', []), $storeSettings)]);
        config(['app.name' => config('settings.app_name') ?: $store->name]);

        return $next($request);
    }
}
