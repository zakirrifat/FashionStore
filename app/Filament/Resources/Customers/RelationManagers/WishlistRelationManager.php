<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\WishlistItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Read-only: what this customer has saved. Useful for service and marketing. */
class WishlistRelationManager extends RelationManager
{
    protected static string $relationship = 'wishlistItems';

    protected static ?string $title = 'Wishlist';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['product', 'colour.images']))
            ->columns([
                ImageColumn::make('thumb')->label('')->disk('public')
                    ->state(fn (WishlistItem $r) => $r->colour?->images->first()?->path),
                TextColumn::make('product.name')->label('Product')
                    ->url(fn (WishlistItem $r) => $r->product ? route('filament.admin.resources.products.edit', $r->product) : null),
                TextColumn::make('colour.name')->label('Colour')->placeholder('—'),
                TextColumn::make('created_at')->label('Saved')->since(),
            ]);
    }
}
