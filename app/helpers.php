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

        return asset('images/srspos.png');
    }
}

if (!function_exists('bill_logo_url')) {
    /**
     * URL of the logo printed on bills: the separate bill logo from Settings, otherwise the normal logo.
     */
    function bill_logo_url(): string
    {
        $logo = config('settings.bill_logo');

        if ($logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($logo);
        }

        return app_logo_url();
    }
}

if (!function_exists('has_custom_logo')) {
    /**
     * True when the store uploaded its own logo in Settings (otherwise the bundled SRSPOS logo is used).
     */
    function has_custom_logo(): bool
    {
        $logo = config('settings.logo');

        return $logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo);
    }
}
