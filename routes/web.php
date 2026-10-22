<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OptionController;
use App\Http\Controllers\Admin\OptionGroupController;
use App\Http\Controllers\Admin\OrderMonitorController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StoreSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\MenuController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\MidtransNotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\Staff\AvailabilityController;
use App\Http\Controllers\Staff\CashierController;
use App\Http\Controllers\Staff\OrderController as StaffOrderController;
use App\Http\Controllers\Staff\PickupController;
use App\Http\Controllers\Staff\QueueController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/kebijakan-privasi', 'privacy')->name('privacy');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
});

Route::post('/midtrans/notification', MidtransNotificationController::class)->name('midtrans.notification');

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Pelanggan
Route::middleware(['auth', 'role:PELANGGAN'])->group(function () {
    Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
    Route::get('/menu/{product}/opsi', [MenuController::class, 'options'])->name('menu.options');

    Route::get('/keranjang', [CartController::class, 'index'])->name('cart.index');
    Route::post('/keranjang/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('/keranjang/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('/keranjang/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');

    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout/voucher', [CheckoutController::class, 'voucher'])->name('checkout.voucher');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/pesanan', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/pesanan/{order:kode_pickup}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/pesanan/{order:kode_pickup}/status', [OrderController::class, 'status'])->name('orders.status');
    Route::post('/pesanan/{order:kode_pickup}/bayar-online', [PaymentController::class, 'online'])->name('orders.pay-online');
    Route::post('/pesanan/{order:kode_pickup}/bayar-kasir', [PaymentController::class, 'cashier'])->name('orders.pay-cashier');
    Route::post('/pesanan/{order:kode_pickup}/batal', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/pesanan/{order:kode_pickup}/pesan-ulang', [OrderController::class, 'reorder'])->name('orders.reorder');
    Route::get('/pesanan/{order:kode_pickup}/struk', [ReceiptController::class, 'show'])->name('orders.receipt');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

// Karyawan (Super Admin juga dapat menjalankan seluruh fitur Karyawan)
Route::middleware(['auth', 'role:KARYAWAN,SUPER_ADMIN'])->prefix('karyawan')->name('staff.')->group(function () {
    Route::get('/', [QueueController::class, 'index'])->name('queue');
    Route::get('/antrean', [QueueController::class, 'data'])->name('queue.data');
    Route::get('/kasir', [CashierController::class, 'index'])->name('cashier');
    Route::get('/pengambilan', [PickupController::class, 'index'])->name('pickup');

    Route::get('/pesanan/cari', [StaffOrderController::class, 'find'])->name('orders.find');
    Route::post('/pesanan/{order:kode_pickup}/konfirmasi-bayar', [CashierController::class, 'confirm'])->name('orders.confirm-payment');
    Route::patch('/pesanan/{order:kode_pickup}/status', [StaffOrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('/pesanan/{order:kode_pickup}/ambil', [PickupController::class, 'confirm'])->name('orders.pickup');
    Route::post('/pesanan/{order:kode_pickup}/batal', [StaffOrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/pesanan/{order:kode_pickup}/struk', [ReceiptController::class, 'show'])->name('orders.receipt');

    Route::get('/riwayat', [StaffOrderController::class, 'history'])->name('history');
    Route::get('/riwayat/{order:kode_pickup}', [StaffOrderController::class, 'show'])->name('history.show');

    Route::get('/ketersediaan', [AvailabilityController::class, 'index'])->name('availability');
    Route::patch('/produk/{product}/ketersediaan', [AvailabilityController::class, 'product'])->name('products.availability');
    Route::patch('/opsi/{option}/ketersediaan', [AvailabilityController::class, 'option'])->name('options.availability');
});

// Super Admin
Route::middleware(['auth', 'role:SUPER_ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::patch('/users/{user}/aktif', [UserController::class, 'toggle'])->name('users.toggle');
    Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');

    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('products', ProductController::class)->except('show');
    Route::resource('vouchers', VoucherController::class)->except('show');

    Route::get('/products/{product}/opsi', [OptionGroupController::class, 'index'])->name('products.options');
    Route::post('/products/{product}/option-groups', [OptionGroupController::class, 'store'])->name('option-groups.store');
    Route::put('/option-groups/{group}', [OptionGroupController::class, 'update'])->name('option-groups.update');
    Route::delete('/option-groups/{group}', [OptionGroupController::class, 'destroy'])->name('option-groups.destroy');
    Route::post('/option-groups/{group}/options', [OptionController::class, 'store'])->name('options.store');
    Route::put('/options/{option}', [OptionController::class, 'update'])->name('options.update');
    Route::delete('/options/{option}', [OptionController::class, 'destroy'])->name('options.destroy');

    Route::get('/pengaturan', [StoreSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/pengaturan', [StoreSettingController::class, 'update'])->name('settings.update');

    Route::get('/pesanan', [OrderMonitorController::class, 'index'])->name('orders.index');
    Route::get('/pesanan/{order:kode_pickup}', [OrderMonitorController::class, 'show'])->name('orders.show');
    Route::get('/transaksi', [OrderMonitorController::class, 'payments'])->name('payments.index');
    Route::post('/pesanan/{order:kode_pickup}/refund', [RefundController::class, 'online'])->name('refunds.online');
    Route::post('/pesanan/{order:kode_pickup}/refund-manual', [RefundController::class, 'manual'])->name('refunds.manual');

    Route::get('/laporan', ReportController::class)->name('reports.sales');
    Route::get('/audit-log', AuditLogController::class)->name('audit');
});
