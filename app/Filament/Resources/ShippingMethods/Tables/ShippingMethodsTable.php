<?php

namespace App\Filament\Resources\ShippingMethods\Tables;

use App\Models\ShippingMethod;
use App\Support\Money;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ShippingMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->description(fn (ShippingMethod $r) => $r->estimate),
                TextColumn::make('zone')->badge()->formatStateUsing(fn ($state) => ShippingMethod::ZONES[$state] ?? $state),
                TextColumn::make('price')->formatStateUsing(fn ($state) => Money::format((int) $state)),
                TextColumn::make('free_over')->label('Free from')->formatStateUsing(fn ($state) => Money::format((int) $state))->placeholder('—'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
