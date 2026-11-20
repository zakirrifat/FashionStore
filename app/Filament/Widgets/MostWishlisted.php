<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Products customers save most — a hint for restocking and marketing. */
class MostWishlisted extends TableWidget
{
    /** Render with the page instead of separate background requests (faster on small servers). */
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Most wishlisted (30 days)';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Product::query()
                ->withCount(['wishlistItems as saves' => fn ($q) => $q->where('created_at', '>=', now()->subDays(30))])
                ->whereHas('wishlistItems', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))
                ->orderByDesc('saves')
                ->limit(5))
            ->paginated(false)
            ->emptyStateHeading('No wishlist saves yet')
            ->columns([
                TextColumn::make('name')->label('Product'),
                TextColumn::make('saves')->label('Saves')->badge(),
            ])
            ->recordActions([
                Action::make('edit')->icon('heroicon-o-pencil-square')
                    ->url(fn (Product $r) => ProductResource::getUrl('edit', ['record' => $r])),
            ]);
    }
}
