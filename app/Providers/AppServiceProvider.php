<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Admin date pickers show/enter local time; the database keeps UTC.
        FilamentTimezone::set(config('app.admin_timezone'));

        // Activity log: staff sign-ins and failed attempts on staff accounts.
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User && $event->user->isStaff()) {
                activity('auth')->causedBy($event->user)->event('login')
                    ->withProperties(['ip' => request()->ip(), 'user_agent' => substr((string) request()->userAgent(), 0, 200)])
                    ->log('Signed in');
            }
        });
        Event::listen(Failed::class, function (Failed $event) {
            $user = $event->user ?? User::where('email', $event->credentials['email'] ?? null)->first();
            if ($user instanceof User && $user->isStaff()) {
                activity('auth')->performedOn($user)->event('failed_login')
                    ->withProperties(['ip' => request()->ip()])
                    ->log('Failed sign-in attempt');
            }
        });

        // Main menu (top-level categories with two levels of children) for every shop page.
        // Shared data for every shop page, loaded once per request.
        View::composer('shop.*', function ($view) {
            $view->with([
                'menu' => once(fn () => Category::query()
                    ->whereNull('parent_id')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with(['children' => fn ($q) => $q->where('is_active', true)->with([
                        'children' => fn ($q) => $q->where('is_active', true),
                    ])])
                    ->get()),
                'settings' => once(fn () => Setting::allValues()),
                'wishlistCount' => once(fn () => \App\Support\Wishlist::count()),
                'bagCount' => once(fn () => \App\Support\ShoppingCart::count()),
                'footerPages' => once(fn () => Page::active()->whereNotNull('footer_group')->orderBy('sort_order')
                    ->get(['title', 'slug', 'footer_group'])->groupBy('footer_group')),
            ]);
        });
    }
}
