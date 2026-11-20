<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Models\Page;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()->columnSpan(2)->schema([
                    TextInput::make('title')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    RichEditor::make('body')
                        ->label('Content')
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'link'],
                            ['h2', 'h3'],
                            ['bulletList', 'orderedList', 'blockquote', 'table'],
                            ['attachFiles', 'horizontalRule'],
                            ['undo', 'redo'],
                        ])
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('pages')
                        ->extraInputAttributes(['style' => 'min-height: 24rem']),
                ]),
                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Publishing')->schema([
                        Toggle::make('is_active')->label('Published')->default(true),
                        TextInput::make('slug')
                            ->label('URL')
                            ->prefix('/pages/')
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        Select::make('footer_group')
                            ->label('Show in footer under')
                            ->options(Page::FOOTER_GROUPS)
                            ->placeholder('Not in footer'),
                        TextInput::make('sort_order')->numeric()->default(0),
                    ]),
                    Section::make('SEO')->collapsed()->schema([
                        TextInput::make('seo_title')->maxLength(70),
                        Textarea::make('seo_description')->maxLength(160)->rows(3),
                    ]),
                ]),
            ]);
    }
}
