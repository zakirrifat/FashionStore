<?php

namespace App\Filament\Resources\Collections\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Collection')
                    ->description('Seasons and edits, e.g. SS27, FW26, Gift Guide, Winter Dressing')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')->label('URL slug')->required()->unique(ignoreRecord: true),
                        TextInput::make('sort_order')->numeric()->default(0)->required(),
                        Toggle::make('is_active')->label('Visible in shop')->default(true)->inline(false),
                        Textarea::make('description')->columnSpanFull(),
                        FileUpload::make('image')
                            ->label('Banner image')
                            ->image()->acceptedFileTypes(\App\Support\Img::UPLOAD_TYPES)
                            ->disk('public')
                            ->directory('collections')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
