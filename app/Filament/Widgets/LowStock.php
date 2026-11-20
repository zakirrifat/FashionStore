<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductVariant;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Sizes of live products that are sold out or running low. */
class LowStock extends TableWidget
{
    /** Render with the page instead of separate background requests (faster on small servers). */
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Stock to reorder';

    public function table(Table $table): Table
    {
        $threshold = (int) Setting::get('low_stock_threshold', 3);

        return $table
            ->query(fn () => ProductVariant::query()
                ->with('colour.product')
                ->whereHas('colour.product', fn ($q) => $q->where('status', 'active'))
                ->where('stock', '<=', $threshold))
            ->defaultSort('stock')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('All live products are well stocked')
            ->columns([
                TextColumn::make('colour.product.name')->label('Product'),
                TextColumn::make('colour.name')->label('Colour'),
                TextColumn::make('size')->formatStateUsing(fn ($state, ProductVariant $r) => trim($state.' '.$r->length)),
                TextColumn::make('sku')->label('SKU')->color('gray'),
                TextColumn::make('stock')->badge()->sortable()
                    ->color(fn (int $state) => $state === 0 ? 'danger' : 'warning'),
            ])
            ->recordActions([
                Action::make('edit')->icon('heroicon-o-pencil-square')
                    ->url(fn (ProductVariant $r) => ProductResource::getUrl('edit', ['record' => $r->colour->product_id]).'?tab=colours-photos-sizes::data::tab'),
            ]);
    }
}
