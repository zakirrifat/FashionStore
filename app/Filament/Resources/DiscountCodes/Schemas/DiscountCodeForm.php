<?php

namespace App\Filament\Resources\DiscountCodes\Schemas;

use App\Filament\Resources\Products\Schemas\ProductForm;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DiscountCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Code')->columns(2)->schema([
                TextInput::make('code')
                    ->required()
                    ->alphaDash()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true)
                    ->placeholder('WELCOME10')
                    ->helperText('Customers type this in their bag. Not case-sensitive.')
                    ->suffixAction(\Filament\Actions\Action::make('generate')->icon('heroicon-o-sparkles')
                        ->action(fn (\Filament\Schemas\Components\Utilities\Set $set) => $set('code', Str::upper(Str::random(8))))),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false),
                Radio::make('type')->options(['percent' => 'Percentage off', 'fixed' => 'Fixed amount off (Rand)'])
                    ->default('percent')->required()->live()->inline(),
                TextInput::make('value')
                    ->label(fn (Get $get) => $get('type') === 'fixed' ? 'Amount off (R)' : 'Percentage off')
                    ->suffix(fn (Get $get) => $get('type') === 'fixed' ? null : '%')
                    ->prefix(fn (Get $get) => $get('type') === 'fixed' ? 'R' : null)
                    ->numeric()->minValue(1)->required()
                    ->maxValue(fn (Get $get) => $get('type') === 'fixed' ? null : 100)
                    // Fixed amounts are stored in cents, percentages as whole numbers.
                    ->formatStateUsing(fn ($state, Get $get) => $state === null ? null : ($get('type') === 'fixed' ? $state / 100 : $state))
                    ->dehydrateStateUsing(fn ($state, Get $get) => $get('type') === 'fixed' ? (int) round(((float) $state) * 100) : (int) $state),
            ]),
            Section::make('Limits (optional)')->columns(2)->schema([
                ProductForm::money('min_subtotal', 'Minimum spend'),
                TextInput::make('max_uses')->label('Total uses allowed')->numeric()->integer()->minValue(1)->placeholder('Unlimited'),
                DateTimePicker::make('starts_at')->label('Valid from'),
                DateTimePicker::make('ends_at')->label('Valid until')->after('starts_at'),
                TextInput::make('used_count')->label('Used so far')->disabled()->dehydrated(false)->visibleOn('edit'),
            ]),
        ]);
    }
}
