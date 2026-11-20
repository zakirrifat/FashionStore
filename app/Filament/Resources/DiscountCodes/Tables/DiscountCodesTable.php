<?php

namespace App\Filament\Resources\DiscountCodes\Tables;

use App\Models\DiscountCode;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class DiscountCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->searchable()->copyable()->weight('medium'),
                TextColumn::make('value')->label('Discount')->state(fn (DiscountCode $r) => $r->label()),
                TextColumn::make('used_count')->label('Used')
                    ->formatStateUsing(fn ($state, DiscountCode $r) => $state.($r->max_uses ? ' / '.$r->max_uses : '')),
                TextColumn::make('ends_at')->label('Ends')->date()->placeholder('No end date')
                    ->color(fn (DiscountCode $r) => $r->ends_at?->isPast() ? 'danger' : null),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
