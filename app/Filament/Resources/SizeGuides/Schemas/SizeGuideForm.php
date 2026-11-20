<?php

namespace App\Filament\Resources\SizeGuides\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SizeGuideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()
                ->description('Attach this guide to categories (Catalogue › Categories) or to a single product. Products use their own guide first, then the nearest category’s guide, then the general Size Guide page.')
                ->schema([
                    TextInput::make('name')->required()->placeholder('Women’s jeans'),
                    RichEditor::make('body')
                        ->label('Guide')
                        ->toolbarButtons([
                            ['bold', 'italic', 'link'],
                            ['h2', 'h3'],
                            ['table', 'bulletList'],
                            ['attachFiles'],
                            ['undo', 'redo'],
                        ])
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('pages')
                        ->helperText('Use the table button to add the size chart. You can also add a “how to measure” picture.')
                        ->extraInputAttributes(['style' => 'min-height: 20rem']),
                ]),
        ]);
    }
}
