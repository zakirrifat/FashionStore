<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Csv;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        $count = fn (string $status) => Order::where('status', $status)->count();

        return [
            'all' => Tab::make('All'),
            'awaiting_payment' => Tab::make('Awaiting payment')->badge($count('awaiting_payment'))->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'awaiting_payment')),
            'paid' => Tab::make('To ship')->badge($count('paid'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid')),
            'shipped' => Tab::make('Shipped')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'shipped')),
            'done' => Tab::make('Completed')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['delivered'])),
            'cancelled' => Tab::make('Cancelled')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled')),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Csv::download(
                    'orders-'.now()->format('Y-m-d').'.csv',
                    ['number', 'placed', 'status', 'name', 'email', 'phone', 'country', 'items', 'subtotal', 'discount', 'delivery', 'total', 'vat', 'paid_at', 'shipped_at'],
                    Order::with('items')->orderBy('id')->lazy()->map(fn (Order $o) => [
                        $o->number, $o->created_at->toDateTimeString(), $o->statusLabel(), $o->customerName(), $o->email, $o->phone,
                        $o->shipping_address['country'] ?? '', $o->itemCount(),
                        $o->subtotal / 100, $o->discount_total / 100, $o->shipping_total / 100, $o->total / 100, $o->vat_total / 100,
                        $o->paid_at?->toDateTimeString(), $o->shipped_at?->toDateTimeString(),
                    ]),
                )),
        ];
    }
}
