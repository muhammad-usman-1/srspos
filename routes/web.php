<?php

use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\PurchaseCartController;
use App\Http\Controllers\Inventory\PurchaseController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Pos\HeldBillController;
use App\Http\Controllers\Management\CustomerController;
use App\Http\Controllers\Management\SupplierController;
use App\Http\Controllers\Pos\CartController;
use App\Http\Controllers\Pos\OrderController;
use App\Http\Controllers\Pos\PosSyncController;
use App\Http\Controllers\Settings\SettingController;
use App\Http\Controllers\SuperAdmin\StoreController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Public landing page
Route::view('/', 'landing')->name('landing');

Auth::routes(['register' => false]);

// Uploaded files (logo, product images). Served by Laravel so it works on hosts where
// the web server does not map /storage (or symlinks are disabled).
Route::get('/media/{path}', function (string $path) {
    abort_if(str_contains($path, '..'), 404);
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    abort_unless($disk->exists($path), 404);

    return $disk->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
})->where('path', '.*')->name('media');

Route::prefix('admin')->middleware(['auth', 'locale', 'store.access'])->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    // Superadmin: store management
    Route::prefix('superadmin')->name('superadmin.')->group(function (): void {
        Route::resource('stores', StoreController::class)->except('show');
        Route::post('/stores/{store}/toggle', [StoreController::class, 'toggle'])->name('stores.toggle');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/settings',[SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'store'])->name('settings.store');
    Route::resource('products', ProductController::class);
    Route::resource('customers', CustomerController::class);
    Route::resource('orders', OrderController::class);
    Route::resource('suppliers', SupplierController::class);

    // POS Cart
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::post('/cart/change-qty', [CartController::class, 'changeQty']);
    Route::delete('/cart/delete', [CartController::class, 'delete']);
    Route::delete('/cart/empty', [CartController::class, 'empty']);

    // Purchases add stock, so they only make sense for stores that track quantity.
    Route::middleware('stock.tracked')->group(function (): void {
        Route::get('/purchases/data', [PurchaseController::class, 'data'])->name('purchases.data');
        Route::get('/purchases/{purchase}/receipt', [PurchaseController::class, 'receipt'])->name('purchases.receipt');
        Route::resource('purchases', PurchaseController::class);

        // Purchase Cart API
        Route::prefix('purchase-cart')->name('purchase-cart.')->group(function (): void {
            Route::get('/', [PurchaseCartController::class, 'index'])->name('index');
            Route::post('/', [PurchaseCartController::class, 'store'])->name('store');
            Route::post('/change-qty', [PurchaseCartController::class, 'changeQty'])->name('change-qty');
            Route::post('/change-price', [PurchaseCartController::class, 'changePrice'])->name('change-price');
            Route::delete('/delete', [PurchaseCartController::class, 'delete'])->name('delete');
            Route::delete('/empty', [PurchaseCartController::class, 'empty'])->name('empty');
        });
    });

    // Offline-capable POS
    Route::get('/pos/bootstrap', [PosSyncController::class, 'bootstrap'])->name('pos.bootstrap');
    Route::post('/pos/sales', [PosSyncController::class, 'sales'])->name('pos.sales');

    // Held bills
    Route::get('/held-bills', [HeldBillController::class, 'index'])->name('held-bills.index');
    Route::post('/held-bills', [HeldBillController::class, 'store'])->name('held-bills.store');
    Route::post('/held-bills/{heldBill}/resume', [HeldBillController::class, 'resume'])->name('held-bills.resume');
    Route::delete('/held-bills/{heldBill}', [HeldBillController::class, 'destroy'])->name('held-bills.destroy');

    // Stock management (hidden for stores in "simple" stock mode)
    Route::middleware('stock.tracked')->group(function (): void {
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stock/movements', [StockController::class, 'movements'])->name('stock.movements');
        Route::post('/stock/{product}/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
    });

    // Orders
    Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');
    Route::post('/orders/partial-payment', [OrderController::class, 'partialPayment'])->name('orders.partial-payment');

    // Translations
    Route::get('/locale/{type}', function ($type) {
        $translations = trans($type);
        return response()->json($translations);
    });

    // Language Switch
    Route::get('/lang-switch/{lang}', function ($lang) {
        $supportedLocales = ['en', 'es'];

        if (in_array($lang, $supportedLocales)) {
            session(['locale' => $lang]);
            app()->setLocale($lang);
        }

        return redirect()->back();
    })->name('lang.switch');
});
