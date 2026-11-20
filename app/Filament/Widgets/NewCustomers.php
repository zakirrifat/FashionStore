<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class NewCustomers extends TableWidget
{
    /** Render with the page instead of separate background requests (faster on small servers). */
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected static ?string $heading = 'Newest customers';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => User::customers()->latest()->limit(5))
            ->paginated(false)
            ->emptyStateHeading('No customers yet')
            ->columns([
                TextColumn::make('name')->description(fn (User $r) => $r->email),
                TextColumn::make('created_at')->label('Joined')->since(),
            ])
            ->recordActions([
                Action::make('open')->icon('heroicon-o-arrow-right')
                    ->url(fn (User $r) => CustomerResource::getUrl('edit', ['record' => $r])),
            ]);
    }
}
