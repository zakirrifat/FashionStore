<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\PriceEnquiries\PriceEnquiryResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\ContactMessage;
use App\Models\PriceEnquiry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Models\WishlistItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreStats extends StatsOverviewWidget
{
    /** Render with the page instead of separate background requests (faster on small servers). */
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $threshold = (int) Setting::get('low_stock_threshold', 3);
        $activeVariants = ProductVariant::whereHas('colour.product', fn ($q) => $q->where('status', 'active'));

        $soldOut = (clone $activeVariants)->where('stock', 0)->count();
        $low = (clone $activeVariants)->whereBetween('stock', [1, $threshold])->count();

        $customers = User::customers()->count();
        $newCustomers = User::customers()->where('created_at', '>=', now()->startOfMonth())->count();

        $unread = ContactMessage::whereNull('read_at')->count();
        $wishlistAdds = WishlistItem::where('created_at', '>=', now()->subDays(30))->count();
        $newEnquiries = PriceEnquiry::where('status', 'new')->count();

        return [
            Stat::make('Live products', Product::where('status', 'active')->count())
                ->description(Product::where('status', 'draft')->count().' drafts')
                ->icon('heroicon-o-shopping-bag')
                ->url(ProductResource::getUrl()),
            Stat::make('Stock alerts', $soldOut + $low)
                ->description("{$soldOut} sold out · {$low} low (≤ {$threshold})")
                ->color($soldOut + $low > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Customers', $customers)
                ->description("{$newCustomers} new this month")
                ->icon('heroicon-o-user-group')
                ->url(CustomerResource::getUrl()),
            Stat::make('Unread messages', $unread)
                ->color($unread > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-envelope')
                ->url(ContactMessageResource::getUrl()),
            Stat::make('Wishlist saves', $wishlistAdds)
                ->description('last 30 days')
                ->icon('heroicon-o-heart'),
            Stat::make('New price enquiries', $newEnquiries)
                ->description(PriceEnquiry::where('created_at', '>=', now()->subDays(30))->count().' in the last 30 days')
                ->color($newEnquiries > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->url(PriceEnquiryResource::getUrl()),
        ];
    }
}
