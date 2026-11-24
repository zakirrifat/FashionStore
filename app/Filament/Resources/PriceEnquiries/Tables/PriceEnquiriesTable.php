<?php

namespace App\Filament\Resources\PriceEnquiries\Tables;

use App\Models\PriceEnquiry;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class PriceEnquiriesTable
{
    public static function statusColor(string $status): string
    {
        return match ($status) {
            'new' => 'warning',
            'contacted' => 'info',
            default => 'gray',
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (PriceEnquiry $r) => $r->status === 'new' ? 'font-semibold' : null)
            ->columns([
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => PriceEnquiry::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => self::statusColor($state)),
                TextColumn::make('name')->searchable()
                    ->description(fn (PriceEnquiry $r) => $r->phone),
                TextColumn::make('email')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('product_name')->label('Product')->searchable()->wrap()
                    ->description(fn (PriceEnquiry $r) => $r->optionsLabel() ?: null),
                TextColumn::make('message')->limit(50)->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Received')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(PriceEnquiry::STATUSES),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    Action::make('call')->icon('heroicon-o-phone')
                        ->url(fn (PriceEnquiry $r) => 'tel:'.preg_replace('/[^\d+]/', '', $r->phone)),
                    Action::make('email')->icon('heroicon-o-envelope')
                        ->visible(fn (PriceEnquiry $r) => filled($r->email))
                        ->url(fn (PriceEnquiry $r) => 'mailto:'.$r->email.'?subject='.rawurlencode('Price for '.$r->product_name)),
                    Action::make('markContacted')->label('Mark as contacted')->icon('heroicon-o-check')
                        ->visible(fn (PriceEnquiry $r) => $r->status === 'new')
                        ->action(fn (PriceEnquiry $r) => $r->update(['status' => 'contacted'])),
                    EditAction::make()->label('Status & note'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markContacted')->label('Mark as contacted')->icon('heroicon-o-check')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'contacted'])),
                    BulkAction::make('markClosed')->label('Mark as closed')->icon('heroicon-o-archive-box')
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'closed'])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
