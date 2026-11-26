<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Category')->columns(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('URL slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Must be unique, e.g. woman-jeans'),
                    Select::make('parent_id')
                        ->label('Parent category')
                        ->relationship('parent', 'name', fn ($query, ?Category $record) => $query->with('parent.parent')->when($record, fn ($q) => $q->whereKeyNot($record->getKey())))
                        ->getOptionLabelFromRecordUsing(fn (Category $record) => $record->full_name)
                        ->searchable()
                        ->preload()
                        // "Add subcategory" in the list links here with ?parent=<id>.
                        ->default(fn () => Category::whereKey(request()->integer('parent'))->value('id'))
                        ->placeholder('None (top level, e.g. Woman, Man, Kids)'),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->required(),
                    Select::make('size_guide_id')
                        ->label('Size guide')
                        ->relationship('sizeGuide', 'name')
                        ->preload()
                        ->placeholder('Use parent category’s guide')
                        ->helperText('Applies to this category and every category below it.'),
                    Toggle::make('is_active')
                        ->label('Visible in shop')
                        ->default(true),
                    Textarea::make('description')->columnSpanFull(),
                    FileUpload::make('image')
                        ->image()->acceptedFileTypes(\App\Support\Img::UPLOAD_TYPES)
                        ->disk('public')
                        ->directory('categories')
                        ->columnSpanFull(),
                    FileUpload::make('header_image')
                        ->label('Page header image')
                        ->helperText('Wide picture shown behind the title at the top of this category’s page. The band is short and wide (about 10:1), so use a landscape photo around 2400 × 240 px; the edges may be cropped on smaller screens.')
                        ->image()->acceptedFileTypes(\App\Support\Img::UPLOAD_TYPES)
                        ->disk('public')
                        ->directory('categories')
                        ->columnSpanFull(),
                ]),
                Section::make('SEO')->collapsed()->schema([
                    TextInput::make('seo_title')->maxLength(70),
                    Textarea::make('seo_description')->maxLength(160),
                ]),
            ]);
    }
}
