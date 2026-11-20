<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\Address;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('first_name')->required(),
            TextInput::make('last_name')->required(),
            TextInput::make('company'),
            TextInput::make('phone')->required(),
            TextInput::make('line1')->label('Street address')->required()->columnSpanFull(),
            TextInput::make('line2')->label('Apartment / complex')->columnSpanFull(),
            TextInput::make('city')->required(),
            TextInput::make('province')->label('Province / state'),
            TextInput::make('postal_code')->required(),
            Select::make('country')->options(Address::countries())->searchable()->default('ZA')->required(),
            Toggle::make('is_default')->label('Default address'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')->label('Name')
                    ->formatStateUsing(fn ($state, Address $r) => $r->first_name.' '.$r->last_name),
                TextColumn::make('line1')->label('Address')
                    ->formatStateUsing(fn ($state, Address $r) => implode(', ', array_filter([$r->line1, $r->line2, $r->city, $r->postal_code])))
                    ->wrap(),
                TextColumn::make('country')->formatStateUsing(fn ($state, Address $r) => $r->countryName()),
                TextColumn::make('phone'),
                IconColumn::make('is_default')->label('Default')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
