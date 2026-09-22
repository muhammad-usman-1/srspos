<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
    {
        return view('settings.edit');
    }

    public function store(Request $request)
    {
        $request->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:2048'],
            'bill_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:2048'],
            'stock_mode' => ['nullable', 'in:tracked,simple'],
        ]);

        $store = $request->user()->store;

        // Write-once: only ever accepted the first time, before the store has a stock
        // mode at all. Anything submitted after that (even a tampered request) is ignored.
        if ($store && !$store->hasChosenStockMode() && $request->filled('stock_mode')) {
            $store->forceFill(['stock_mode' => $request->input('stock_mode')])->save();
        }

        $storeId = $request->user()->store_id;
        $data = $request->except(['_token', 'logo', 'remove_logo', 'bill_logo', 'remove_bill_logo', 'stock_mode']);
        foreach ($data as $key => $value) {
            $setting = Setting::firstOrCreate(['key' => $key, 'store_id' => $storeId]);
            $setting->value = $value;
            $setting->save();
        }

        $current = config('settings.logo');
        if ($request->hasFile('logo')) {
            if ($current) {
                Storage::disk('public')->delete($current);
            }
            Setting::updateOrCreate(['key' => 'logo', 'store_id' => $storeId], ['value' => $request->file('logo')->store('logos', 'public')]);
        } elseif ($request->boolean('remove_logo') && $current) {
            Storage::disk('public')->delete($current);
            Setting::where('key', 'logo')->where('store_id', $storeId)->delete();
        }

        $currentBill = config('settings.bill_logo');
        if ($request->hasFile('bill_logo')) {
            if ($currentBill) {
                Storage::disk('public')->delete($currentBill);
            }
            Setting::updateOrCreate(['key' => 'bill_logo', 'store_id' => $storeId], ['value' => $request->file('bill_logo')->store('logos', 'public')]);
        } elseif ($request->boolean('remove_bill_logo') && $currentBill) {
            Storage::disk('public')->delete($currentBill);
            Setting::where('key', 'bill_logo')->where('store_id', $storeId)->delete();
        }

        return redirect()->route('settings.index')->with('success', __('Settings saved.'));
    }
}
