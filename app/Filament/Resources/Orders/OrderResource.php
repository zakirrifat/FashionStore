<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    /** Hidden (menu and URLs) while checkout is switched off — see config/shop.php. */
    public static function canAccess(): bool
    {
        return config('shop.checkout_enabled') && parent::canAccess();
    }

    protected static ?string $recordTitleAttribute = 'number';

    /** Orders waiting for the team to arrange payment. */
    public static function getNavigationBadge(): ?string
    {
        $n = Order::where('status', 'awaiting_payment')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'email', 'phone'];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false; // cancel instead, so stock and history stay correct
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = fn (string $field) => TextEntry::make($field)->formatStateUsing(fn ($state) => Money::format((int) $state));

        return $schema->columns(3)->components([
            Section::make('Items')->columnSpan(2)->schema([
                RepeatableEntry::make('items')->hiddenLabel()->columns(5)->schema([
                    TextEntry::make('name')->label('Product')->columnSpan(2)
                        ->url(fn ($record) => $record->product_id ? route('filament.admin.resources.products.edit', $record->product_id) : null),
                    TextEntry::make('colour')->formatStateUsing(fn ($state, $record) => $state.' · '.$record->sizeLabel())->label('Colour / size'),
                    TextEntry::make('quantity')->label('Qty'),
                    TextEntry::make('line_total')->label('Total')->formatStateUsing(fn ($state) => Money::format((int) $state)),
                ]),
                Grid::make(3)->schema([
                    $money('subtotal'),
                    $money('discount_total')->label('Discount')
                        ->formatStateUsing(fn ($state, Order $r) => $state ? '−'.Money::format((int) $state).' ('.$r->discount_code.')' : '—'),
                    $money('shipping_total')->label('Delivery'),
                    $money('gift_wrap_total')->label('Gift wrap')->visible(fn (Order $r) => $r->gift_wrap),
                    $money('total')->weight('bold'),
                    TextEntry::make('vat_total')->label('VAT')
                        ->formatStateUsing(fn ($state, Order $r) => $r->vat_exempt ? 'Export — no VAT' : 'incl. '.Money::format((int) $state)),
                ]),
            ]),
            Grid::make(1)->columnSpan(1)->schema([
                Section::make('Status')->schema([
                    TextEntry::make('status')->badge()
                        ->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? $state)
                        ->color(fn ($state) => Order::STATUS_COLOURS[$state] ?? 'gray'),
                    TextEntry::make('created_at')->label('Placed')->dateTime(),
                    TextEntry::make('paid_at')->label('Paid')->dateTime()->placeholder('—'),
                    TextEntry::make('shipped_at')->label('Shipped')->dateTime()->placeholder('—')
                        ->helperText(fn (Order $r) => trim(($r->courier ?? '').' '.($r->tracking_number ?? '')) ?: null),
                    TextEntry::make('cancelled_at')->label('Cancelled')->dateTime()->visible(fn (Order $r) => $r->cancelled_at !== null),
                ]),
                Section::make('Customer')->schema([
                    TextEntry::make('customer')->hiddenLabel()
                        ->state(fn (Order $r) => $r->customerName())
                        ->helperText(fn (Order $r) => $r->user_id ? 'Has an account' : 'Guest checkout'),
                    TextEntry::make('email')->copyable()->url(fn (Order $r) => 'mailto:'.$r->email.'?subject='.rawurlencode('Your order '.$r->number)),
                    TextEntry::make('phone')->copyable()->url(fn (Order $r) => 'tel:'.preg_replace('/[^0-9+]/', '', $r->phone)),
                    TextEntry::make('address')->label('Deliver to')->state(fn (Order $r) => implode("\n", $r->addressLines()))
                        ->extraAttributes(['style' => 'white-space: pre-line']),
                    TextEntry::make('shipping_method')->label('Delivery'),
                    TextEntry::make('gift_message')->label('Gift message')
                        ->state(fn (Order $r) => $r->gift_wrap ? ($r->gift_message ?: 'Gift wrap, no message') : null)
                        ->visible(fn (Order $r) => $r->gift_wrap),
                    TextEntry::make('customer_note')->label('Customer note')->visible(fn (Order $r) => filled($r->customer_note)),
                    TextEntry::make('admin_note')->label('Internal note')->placeholder('—'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->searchable()->sortable()->weight('medium'),
                TextColumn::make('created_at')->label('Placed')->since()->sortable()
                    ->tooltip(fn (Order $r) => $r->created_at->timezone(config('app.admin_timezone'))->format('j M Y H:i')),
                TextColumn::make('customer')->state(fn (Order $r) => $r->customerName())
                    ->description(fn (Order $r) => $r->email)
                    ->searchable(query: fn (Builder $q, string $search) => $q->where('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")),
                TextColumn::make('country')->state(fn (Order $r) => $r->shipping_address['country'] ?? '')->label('Ships to'),
                TextColumn::make('total')->formatStateUsing(fn ($state) => Money::format((int) $state))->sortable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => Order::STATUS_COLOURS[$state] ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Order::STATUSES)->multiple(),
                Filter::make('export')->label('Shipped abroad')->query(fn (Builder $q) => $q->where('vat_exempt', true)),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
