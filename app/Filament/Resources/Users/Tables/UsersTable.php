<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => User::ROLES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'admin' ? 'danger' : 'warning'),
                IconColumn::make('two_step')->label('Two-step sign-in')->boolean()
                    ->state(fn (User $r) => filled($r->app_authentication_secret)),
                TextColumn::make('created_at')->label('Added')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(['staff' => 'Staff', 'admin' => 'Admin']),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('reset2fa')
                    ->label('Reset two-step')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (User $r) => filled($r->app_authentication_secret) && $r->id !== auth()->id())
                    ->requiresConfirmation()
                    ->modalDescription('Use this if they lost their phone and recovery codes. They will set up two-step sign-in again next time they sign in.')
                    ->action(function (User $r) {
                        $r->saveAppAuthenticationSecret(null);
                        $r->saveAppAuthenticationRecoveryCodes(null);
                        activity('auth')->performedOn($r)->event('2fa_reset')->log('Two-step sign-in reset by admin');
                        Notification::make()->success()->title('Two-step sign-in reset')->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (User $r) => $r->id !== auth()->id())
                    ->modalDescription('Removes their admin access and account.'),
            ]);
    }
}
