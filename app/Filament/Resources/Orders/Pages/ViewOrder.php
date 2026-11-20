<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'Order '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        /** @var Order $order */
        $order = $this->record;

        return [
            Action::make('markPaid')
                ->label('Mark as paid')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $order->status === 'awaiting_payment')
                ->requiresConfirmation()
                ->modalDescription('Confirm the bank transfer for '.\App\Support\Money::format($order->total).' (reference '.$order->number.') has arrived. The customer gets a “payment received” email.')
                ->action(function () use ($order) {
                    OrderService::markPaid($order);
                    $this->notify('Marked as paid — customer emailed.');
                }),

            Action::make('ship')
                ->label('Mark as shipped')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->visible(fn () => in_array($order->status, ['awaiting_payment', 'paid'], true))
                ->modalDescription(fn () => $order->status === 'awaiting_payment' ? 'This order is not marked as paid yet. Shipping it will also mark it as paid.' : null)
                ->schema([
                    TextInput::make('courier')->placeholder('The Courier Guy, Aramex, DHL…'),
                    TextInput::make('tracking_number'),
                    TextInput::make('tracking_url')->label('Tracking link')->url()->placeholder('https://…'),
                ])
                ->action(function (array $data) use ($order) {
                    OrderService::markShipped($order, $data['courier'] ?? null, $data['tracking_number'] ?? null, $data['tracking_url'] ?? null);
                    $this->notify('Marked as shipped — customer emailed with tracking.');
                }),

            Action::make('delivered')
                ->label('Mark as delivered')
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->visible(fn () => $order->status === 'shipped')
                ->requiresConfirmation()
                ->action(function () use ($order) {
                    OrderService::markDelivered($order);
                    $this->notify('Marked as delivered.');
                }),

            ActionGroup::make([
                Action::make('note')
                    ->label('Internal note')
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn () => ['admin_note' => $order->admin_note])
                    ->schema([Textarea::make('admin_note')->label('Only your team sees this')->rows(4)])
                    ->action(function (array $data) use ($order) {
                        $order->update(['admin_note' => $data['admin_note']]);
                        $this->notify('Note saved.');
                    }),
                Action::make('resendConfirmation')
                    ->label('Email order details again')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->action(function () use ($order) {
                        \Illuminate\Support\Facades\Mail::to($order->email)->send(new \App\Mail\OrderPlaced($order));
                        $this->notify('Email sent to '.$order->email.'.');
                    }),
                Action::make('openShop')
                    ->label('Customer’s order page')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url($order->confirmationUrl())
                    ->openUrlInNewTab(),
                Action::make('cancel')
                    ->label('Cancel order')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => in_array($order->status, ['awaiting_payment', 'paid'], true))
                    ->requiresConfirmation()
                    ->modalDescription('Items go back into stock. If the customer already paid, refund them by bank transfer.')
                    ->schema([Toggle::make('notify')->label('Email the customer')->default(true)])
                    ->action(function (array $data) use ($order) {
                        OrderService::cancel($order, (bool) ($data['notify'] ?? true));
                        $this->notify('Order cancelled and stock restored.');
                    }),
            ])->label('More')->button()->color('gray'),
        ];
    }

    private function notify(string $message): void
    {
        $this->record->refresh();
        Notification::make()->success()->title($message)->send();
    }
}
