<?php
if (!function_exists('activeSegment')) {
    function activeSegment($name, $segment = 2, $class = 'active')
    {
        return request()->segment($segment) == $name ? $class : '';
    }
}

if (!function_exists('store_tracks_stock')) {
    /**
     * True when this store's chosen stock mode is "tracked" (quantities, low-stock
     * alerts). False when it picked "simple" (products only, no quantities).
     */
    function store_tracks_stock(): bool
    {
        return config('settings.stock_mode', \App\Models\Store::STOCK_MODE_TRACKED) !== \App\Models\Store::STOCK_MODE_SIMPLE;
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

if (!function_exists('activity_log')) {
    /**
     * Record something that happened in the store for the activity / audit trail.
     * Never throws: a logging failure must not break a sale or a purchase.
     *
     * @param  string  $subjectType  short type: order, purchase, product, ...
     */
    function activity_log(string $action, string $description, ?string $subjectType = null, ?int $subjectId = null, array $properties = []): void
    {
        try {
            \App\Models\ActivityLog::create([
                'store_id' => auth()->user()?->store_id,
                'user_id' => auth()->id(),
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'description' => mb_substr($description, 0, 255),
                'properties' => $properties ?: null,
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
