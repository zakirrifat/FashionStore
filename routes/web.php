<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::name('shop.')->group(function () {
    Route::controller(ShopController::class)->group(function () {
        Route::get('/', 'home')->name('home');
        Route::get('/search', 'search')->name('search');
        Route::get('/search/suggest', 'suggest')->name('search.suggest')->middleware('throttle:120,1');
        Route::get('/c/{category:slug}', 'category')->name('category');
        Route::get('/collections/{collection:slug}', 'collection')->name('collection');
        Route::get('/p/{product:slug}', 'product')->name('product');
    });

    Route::controller(ContentController::class)->group(function () {
        Route::get('/pages/{slug}', 'page')->name('page');
        Route::get('/faq', 'faq')->name('faq');
        Route::get('/contact', 'contact')->name('contact');
        Route::post('/contact', 'sendContact')->name('contact.send')->middleware('throttle:5,10');
    });
});

/* ───────── Resized images (created on first request) ───────── */
Route::get('/img/{width}/{path}', [\App\Http\Controllers\ImageController::class, 'show'])
    ->name('img')
    ->whereNumber('width')
    ->where('path', '.*');

/* ───────── Bag & checkout (guests and customers) — off in catalogue mode, see config/shop.php ───────── */
Route::middleware('checkout')->controller(\App\Http\Controllers\CartController::class)->group(function () {
    Route::get('/bag', 'show')->name('bag');
    Route::get('/bag/drawer', 'drawer')->name('bag.drawer');
    Route::post('/bag', 'add')->name('bag.add')->middleware('throttle:60,1');
    Route::patch('/bag/{item}', 'update')->name('bag.update')->whereNumber('item');
    Route::delete('/bag/{item}', 'remove')->name('bag.remove')->whereNumber('item');
    Route::post('/bag/discount', 'applyDiscount')->name('bag.discount')->middleware('throttle:10,1');
    Route::delete('/bag/discount', 'removeDiscount')->name('bag.discount.remove');
});
Route::middleware('checkout')->controller(\App\Http\Controllers\CheckoutController::class)->group(function () {
    Route::get('/checkout', 'show')->name('checkout');
    Route::get('/checkout/quote', 'quote')->name('checkout.quote')->middleware('throttle:120,1');
    Route::post('/checkout', 'place')->name('checkout.place')->middleware('throttle:10,1');
    Route::get('/order/{number}/{token}', 'confirmation')->name('order.show');
});

/* ───────── Ask for price ───────── */
Route::post('/enquiry', [\App\Http\Controllers\EnquiryController::class, 'store'])
    ->name('enquiry.store')
    ->middleware('throttle:5,10');

/* ───────── Wishlist (guests and customers) ───────── */
Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle')->middleware('throttle:60,1');

/* ───────── Sign in / register / password reset ───────── */
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

/* ───────── My account ───────── */
Route::middleware('auth')->prefix('account')->name('account.')->controller(AccountController::class)->group(function () {
    Route::get('/', 'overview')->name('overview');
    Route::get('/details', 'editProfile')->name('profile');
    Route::put('/details', 'updateProfile')->name('profile.update');
    Route::put('/password', 'updatePassword')->name('password.update');

    // Orders and delivery addresses only matter when checkout is on.
    Route::middleware('checkout')->group(function () {
        Route::get('/orders', 'orders')->name('orders');
        Route::get('/orders/{number}', 'order')->name('orders.show');

        Route::get('/addresses', 'addresses')->name('addresses');
        Route::get('/addresses/new', 'createAddress')->name('addresses.create');
        Route::post('/addresses', 'storeAddress')->name('addresses.store');
        Route::get('/addresses/{address}/edit', 'editAddress')->name('addresses.edit');
        Route::put('/addresses/{address}', 'updateAddress')->name('addresses.update');
        Route::delete('/addresses/{address}', 'destroyAddress')->name('addresses.destroy');
    });
});
