<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('User')->columns(2)->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                    Select::make('role')
                        ->options(['staff' => 'Staff', 'admin' => 'Admin'])
                        ->default('staff')
                        ->required()
                        ->helperText('Staff: products, content, customers. Admin: also team, store settings and activity log. To remove access, delete the team member.'),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->rule('min:10')
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->helperText('Leave empty to keep the current password'),
                ]),
            ]);
    }
}
