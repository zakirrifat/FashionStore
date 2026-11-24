<?php

namespace App\Filament\Resources\PriceEnquiries;

use App\Filament\Resources\PriceEnquiries\Pages\ListPriceEnquiries;
use App\Filament\Resources\PriceEnquiries\Tables\PriceEnquiriesTable;
use App\Models\PriceEnquiry;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PriceEnquiryResource extends Resource
{
    protected static ?string $model = PriceEnquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Price enquiries';

    protected static ?string $modelLabel = 'price enquiry';

    protected static ?int $navigationSort = 10;

    /** Number of new (not yet handled) enquiries shown in the menu. */
    public static function getNavigationBadge(): ?string
    {
        $count = PriceEnquiry::where('status', 'new')->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Admins only update the follow-up fields; what the customer sent stays as is. */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')->options(PriceEnquiry::STATUSES)->required()->native(false),
            Textarea::make('admin_note')->label('Internal note')->rows(4)
                ->placeholder('e.g. Called on Monday, sent the price, will order next week'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('product_name')->label('Product')
                ->url(fn (PriceEnquiry $r) => $r->product ? route('shop.product', $r->product) : null, true),
            TextEntry::make('options')->label('Colour / size')
                ->state(fn (PriceEnquiry $r) => $r->optionsLabel() ?: null)->placeholder('—'),
            TextEntry::make('sku')->label('SKU')->placeholder('—')->copyable(),
            TextEntry::make('created_at')->label('Received')->dateTime(),
            TextEntry::make('name'),
            TextEntry::make('phone')->copyable()->url(fn (PriceEnquiry $r) => 'tel:'.$r->phone),
            TextEntry::make('email')->placeholder('—')->copyable(),
            TextEntry::make('status')->badge()
                ->formatStateUsing(fn (string $state) => PriceEnquiry::STATUSES[$state] ?? $state)
                ->color(fn (string $state) => PriceEnquiriesTable::statusColor($state)),
            TextEntry::make('message')->placeholder('—')->columnSpanFull()
                ->extraAttributes(['style' => 'white-space: pre-line']),
            TextEntry::make('admin_note')->label('Internal note')->placeholder('—')->columnSpanFull()
                ->extraAttributes(['style' => 'white-space: pre-line']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return PriceEnquiriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceEnquiries::route('/'),
        ];
    }
}
