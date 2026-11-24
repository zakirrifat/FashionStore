<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Models\Page;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                self::teamSection(),
                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Publishing')->schema([
                        Toggle::make('is_active')->label('Published')->default(true),
                        TextInput::make('slug')
                            ->label('URL')
                            ->prefix('/pages/')
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            // The leadership section belongs to /pages/about, so that address stays fixed.
                            ->disabled(fn (?Page $record) => $record?->isAbout())
                            ->dehydrated(fn (?Page $record) => ! $record?->isAbout())
                            ->helperText(fn (?Page $record) => $record?->isAbout() ? 'Fixed for the About page.' : null),
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

    /** "Meet Our Leadership": shown under the About page content. Only on the About page. */
    private static function teamSection(): Section
    {
        return Section::make('Leadership section')
            ->description('Shown under the page content. Leave it switched off or empty to hide it.')
            ->icon('heroicon-o-user-group')
            ->columnSpan(2)
            ->visible(fn (?Page $record) => $record?->isAbout())
            ->statePath('team_section')
            ->columns(2)
            ->schema([
                Toggle::make('enabled')->label('Show this section')->default(true)->columnSpanFull(),
                TextInput::make('heading')
                    ->label('Section heading')
                    ->required()
                    ->maxLength(80)
                    ->placeholder('Meet Our Leadership'),
                Select::make('shape')
                    ->label('Photo shape')
                    ->options(Page::PHOTO_SHAPES)
                    ->default('portrait')
                    ->required()
                    ->live()
                    ->helperText('Used for every photo, so they line up.'),
                TextInput::make('subtitle')
                    ->label('Text under the heading')
                    ->maxLength(160)
                    ->placeholder('The people behind our vision and global partnerships.')
                    ->columnSpanFull(),
                Repeater::make('members')
                    ->label('Leaders')
                    ->helperText('Click "Add leader" to add a person: each one has a photo upload, name and designation. Drag to change the order.')
                    ->addActionLabel('Add leader')
                    ->defaultItems(0)
                    ->reorderableWithDragAndDrop()
                    ->collapsible()
                    ->itemLabel(fn (array $state): string => collect([$state['name'] ?? null, $state['designation'] ?? null])->filter()->implode(' — ') ?: 'New leader')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        FileUpload::make('photo')
                            ->label('Photo')
                            ->image()
                            ->disk('public')
                            ->directory('pages/team')
                            ->maxSize(10240)
                            ->imageEditor()
                            // Crop to the chosen shape on upload, so every face sits the same way.
                            ->imageCropAspectRatio(fn (Get $get) => $get('../../shape') === 'portrait' ? '4:5' : '1:1')
                            ->imageEditorAspectRatios(['4:5', '1:1'])
                            ->helperText('Head-and-shoulders photo, at least 800 px wide. Initials show if left empty.')
                            ->columnSpan(1),
                        Grid::make(2)->columnSpan(2)->schema([
                            TextInput::make('name')->required()->maxLength(80)->placeholder('Umar Faruk'),
                            TextInput::make('designation')->required()->maxLength(80)->placeholder('Managing Director'),
                            TextInput::make('tag')
                                ->label('Small tag (optional)')
                                ->maxLength(30)
                                ->placeholder('Leadership')
                                ->helperText('Shown as "01 / Leadership". The number is added for you.'),
                            TextInput::make('link')
                                ->label('LinkedIn or profile link (optional)')
                                ->url()
                                ->maxLength(255)
                                ->placeholder('https://www.linkedin.com/in/…'),
                            Textarea::make('bio')
                                ->label('Short bio (optional)')
                                ->rows(2)
                                ->maxLength(220)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
