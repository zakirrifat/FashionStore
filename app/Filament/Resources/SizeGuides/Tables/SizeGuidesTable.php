<?php

namespace App\Filament\Resources\SizeGuides\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SizeGuidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('categories_count')->counts('categories')->label('Categories'),
                TextColumn::make('products_count')->counts('products')->label('Products'),
                TextColumn::make('updated_at')->label('Updated')->since(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
