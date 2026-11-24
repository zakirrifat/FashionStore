<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent.parent'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')->disk('public')->label(''),
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Category $record) => $record->parent ? $record->parent->full_name : 'Top level'),
                TextColumn::make('slug')->searchable()->color('gray'),
                TextColumn::make('children_count')->counts('children')->label('Subcategories'),
                TextColumn::make('products_count')->counts('products')->label('Products'),
                ToggleColumn::make('is_active')->label('Visible'),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->relationship('parent', 'name', fn ($query) => $query->with('parent.parent'))
                    ->getOptionLabelFromRecordUsing(fn (Category $record) => $record->full_name),
                SelectFilter::make('level')
                    ->label('Level')
                    ->options(['top' => 'Top level only (Woman, Man, Kids…)'])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] === 'top', fn ($q) => $q->whereNull('parent_id'))),
            ])
            ->recordActions([
                Action::make('addChild')
                    ->label('Add subcategory')
                    ->icon(Heroicon::OutlinedPlus)
                    ->color('gray')
                    ->url(fn (Category $record) => CategoryResource::getUrl('create', ['parent' => $record->id])),
                EditAction::make(),
                self::guardDelete(DeleteAction::make()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records, DeleteBulkAction $action) {
                            $ids = $records->modelKeys();
                            $blocked = $records->filter(fn (Category $c) => $c->children()->whereNotIn('id', $ids)->exists());
                            if ($blocked->isNotEmpty()) {
                                Notification::make()->danger()
                                    ->title('Some categories still have subcategories')
                                    ->body('Delete or move the subcategories of '.$blocked->pluck('name')->join(', ').' first, or select them too.')
                                    ->send();
                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }

    /**
     * Deleting a parent would otherwise turn its subcategories into top-level
     * menu items (parent_id is set to null), so refuse until they're gone.
     */
    public static function guardDelete(DeleteAction $action): DeleteAction
    {
        return $action->before(function (Category $record, DeleteAction $action) {
            if ($record->children()->exists()) {
                Notification::make()->danger()
                    ->title('“'.$record->name.'” still has subcategories')
                    ->body('Delete them or move them to another parent first.')
                    ->send();
                $action->halt();
            }
        });
    }
}
