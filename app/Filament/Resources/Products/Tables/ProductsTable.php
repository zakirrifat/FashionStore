<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('colours.images'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->disk('public')
                    ->state(fn (Product $record) => $record->colours->first()?->images->first()?->path),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Product $record) => $record->style_code),
                TextColumn::make('style_code')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gender')
                    ->formatStateUsing(fn (string $state) => Product::GENDERS[$state] ?? $state),
                TextColumn::make('colours_count')
                    ->counts('colours')
                    ->label('Colours'),
                TextColumn::make('price')
                    ->money('ZAR', divideBy: 100)
                    ->sortable(),
                TextColumn::make('variants_sum_stock')
                    ->sum('variants', 'stock')
                    ->label('Stock')
                    ->badge()
                    ->color(fn ($state) => (int) $state === 0 ? 'danger' : ((int) $state < 5 ? 'warning' : 'success')),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Product::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'draft' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Product::STATUSES),
                SelectFilter::make('gender')->options(Product::GENDERS),
                SelectFilter::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
