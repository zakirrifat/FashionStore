<?php

namespace App\Filament\Resources\ShippingMethods\Schemas;

use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\ShippingMethod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShippingMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->required()->placeholder('Standard delivery'),
                Select::make('zone')->options(ShippingMethod::ZONES)->required()->default('za')
                    ->helperText('South Africa = delivery addresses in SA. International = everywhere else.'),
                ProductForm::money('price', 'Price')->required(),
                ProductForm::money('free_over', 'Free when order is at least')
                    ->helperText('After discounts. Leave empty for never free.'),
                TextInput::make('estimate')->label('Delivery time shown to customers')->placeholder('2–5 working days'),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_active')->label('Available at checkout')->default(true),
            ]),
        ]);
    }
}
