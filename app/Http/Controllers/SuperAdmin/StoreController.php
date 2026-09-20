<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\HeldBill;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(): View
    {
        $stores = Store::with('owner')->withCount('products')->orderBy('name')->get();

        $orders = DB::table('orders')
            ->selectRaw('store_id, COUNT(*) as orders_count, COALESCE(SUM(discount), 0) as discounts, COALESCE(SUM(tax_amount), 0) as taxes')
            ->groupBy('store_id')->get()->keyBy('store_id');
        $gross = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->selectRaw('orders.store_id, COALESCE(SUM(order_items.price), 0) as gross')
            ->groupBy('orders.store_id')->pluck('gross', 'store_id');

        $stores->each(function (Store $store) use ($orders, $gross): void {
            $o = $orders[$store->id] ?? null;
            $store->orders_count = (int) ($o->orders_count ?? 0);
            $store->total_sales = (float) ($gross[$store->id] ?? 0) - (float) ($o->discounts ?? 0) + (float) ($o->taxes ?? 0);
        });

        return view('superadmin.stores.index', ['stores' => $stores]);
    }

    public function create(): View
    {
        return view('superadmin.stores.form', ['store' => new Store(['is_active' => true]), 'owner' => new User()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($data): void {
            $store = Store::create(['name' => $data['name'], 'is_active' => true]);

            $owner = new User();
            $owner->forceFill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_STORE_ADMIN,
                'store_id' => $store->id,
            ])->save();

            foreach ($this->defaultSettings($store) as $key => $value) {
                Setting::create(['key' => $key, 'value' => $value, 'store_id' => $store->id]);
            }
        });

        return redirect()->route('superadmin.stores.index')->with('success', __('Store created. They can now log in with the email and password you set.'));
    }

    public function edit(Store $store): View
    {
        return view('superadmin.stores.form', ['store' => $store, 'owner' => $store->owner ?? new User()]);
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $owner = $store->owner;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($owner?->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $store, $owner, $request): void {
            $store->update(['name' => $data['name'], 'is_active' => $request->boolean('is_active')]);

            if ($owner) {
                $owner->first_name = $data['first_name'];
                $owner->last_name = $data['last_name'];
                $owner->email = $data['email'];
                if (!empty($data['password'])) {
                    $owner->password = Hash::make($data['password']);
                }
                $owner->save();
            }
        });

        return redirect()->route('superadmin.stores.index')->with('success', __('Store updated.'));
    }

    public function toggle(Store $store): RedirectResponse
    {
        $store->update(['is_active' => !$store->is_active]);

        return back()->with('success', $store->is_active ? __('Store activated.') : __('Store suspended. Its users can no longer log in.'));
    }

    public function destroy(Store $store): RedirectResponse
    {
        DB::transaction(function () use ($store): void {
            $logo = Setting::where('store_id', $store->id)->where('key', 'logo')->value('value');
            if ($logo) {
                Storage::disk('public')->delete($logo);
            }

            // Users cascade to orders, payments, purchases, customers and carts;
            // products cascade to order/purchase items and stock movements.
            $store->users()->delete();
            Product::withoutGlobalScopes()->where('store_id', $store->id)->delete();
            Customer::withoutGlobalScopes()->where('store_id', $store->id)->delete();
            Supplier::withoutGlobalScopes()->where('store_id', $store->id)->delete();
            HeldBill::withoutGlobalScopes()->where('store_id', $store->id)->delete();
            Setting::where('store_id', $store->id)->delete();
            $store->delete();
        });

        return redirect()->route('superadmin.stores.index')->with('success', __('Store and all of its data deleted.'));
    }

    /** Starting settings for a new store (mirrors SettingsSeeder). */
    private function defaultSettings(Store $store): array
    {
        return [
            'app_name' => $store->name,
            'currency_symbol' => 'PKR',
            'warning_quantity' => '10',
            'enable_discount' => '1',
            'enable_tax' => '0',
            'tax_name' => 'GST',
            'tax_rate' => '0',
        ];
    }
}
