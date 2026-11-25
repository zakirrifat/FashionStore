<?php

namespace App\Filament\Resources\ContactMessages\Tables;

use App\Models\ContactMessage;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ContactMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (ContactMessage $record) => $record->read_at ? null : 'font-semibold')
            ->columns([
                IconColumn::make('read_at')
                    ->label('')
                    ->icon(fn ($state) => $state ? 'heroicon-o-envelope-open' : 'heroicon-s-envelope')
                    ->color(fn ($state) => $state ? 'gray' : 'primary'),
                TextColumn::make('name')->searchable(['name', 'phone', 'email'])
                    ->description(fn (ContactMessage $r) => collect([$r->phone, $r->email])->filter()->implode(' · ')),
                TextColumn::make('subject')->badge()->color('gray'),
                TextColumn::make('message')->limit(60)->searchable(),
                TextColumn::make('created_at')->label('Received')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('read_at')->label('Read')->nullable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->mutateRecordDataUsing(function (array $data, ContactMessage $record): array {
                        $record->read_at ?? $record->update(['read_at' => now()]);

                        return $data;
                    }),
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn (ContactMessage $r) => str_starts_with(self::dial($r->phone), '+'))
                    ->url(fn (ContactMessage $r) => 'https://wa.me/'.ltrim(self::dial($r->phone), '+'), shouldOpenInNewTab: true),
                Action::make('call')
                    ->icon('heroicon-o-phone')
                    ->color('gray')
                    ->visible(fn (ContactMessage $r) => strlen(self::dial($r->phone)) >= 6)
                    ->url(fn (ContactMessage $r) => 'tel:'.self::dial($r->phone)),
                Action::make('reply')
                    ->label('Email')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (ContactMessage $r) => filled($r->email))
                    ->url(fn (ContactMessage $r) => 'mailto:'.$r->email.'?subject='.rawurlencode('Re: '.$r->subject)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    /** Phone number reduced to + and digits, for tel: and wa.me links. */
    private static function dial(?string $phone): string
    {
        return preg_replace('/[^\d+]/', '', (string) $phone);
    }
}
