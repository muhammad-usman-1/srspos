<?php
if (!function_exists('activeSegment')) {
    function activeSegment($name, $segment = 2, $class = 'active')
    {
        return request()->segment($segment) == $name ? $class : '';
    }
}

if (!function_exists('app_logo_url')) {
    /**
     * URL of the logo uploaded in Settings, falling back to the bundled logo.
     */
    function app_logo_url(): string
    {
        $logo = config('settings.logo');

        if ($logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($logo);
        }

        return asset('images/logo.png');
    }
}
