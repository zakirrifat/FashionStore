<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\User;
use App\Support\Csv;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Csv::download(
                    'customers-'.now()->format('Y-m-d').'.csv',
                    ['name', 'email', 'phone', 'newsletter', 'joined', 'country', 'city'],
                    User::customers()->with(['addresses' => fn ($q) => $q->where('is_default', true)])
                        ->orderBy('id')->lazy()
                        ->map(fn (User $u) => [
                            $u->name, $u->email, $u->phone, $u->marketing_opt_in ? 'yes' : 'no',
                            $u->created_at?->toDateString(),
                            $u->addresses->first()?->country, $u->addresses->first()?->city,
                        ]),
                )),
        ];
    }
}
