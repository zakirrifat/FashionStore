<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Product')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Details')->schema([
                            Section::make('Basics')->columns(2)->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                                        if ($operation === 'create') {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->label('URL slug')
                                    ->required()
                                    ->alphaDash()
                                    ->unique(ignoreRecord: true),
                                TextInput::make('style_code')
                                    ->label('Style code')
                                    ->helperText('Base of every SKU, e.g. C00081')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                TextInput::make('tagline')
                                    ->placeholder('Low waist, baggy leg'),
                                Select::make('gender')
                                    ->options(Product::GENDERS)
                                    ->default('unisex')
                                    ->required(),
                                Select::make('status')
                                    ->options(Product::STATUSES)
                                    ->default('draft')
                                    ->required(),
                                DateTimePicker::make('published_at')
                                    ->label('Publish from')
                                    ->helperText('Leave empty to publish as soon as status is Active'),
                                Toggle::make('is_featured')
                                    ->label('Featured on home page')
                                    ->inline(false),
                            ]),

                            Section::make('Price (ZAR)')
                                ->description(config('shop.checkout_enabled') ? null : 'For your own records only — prices are not shown in the shop. Customers use “Ask for price”.')
                                ->columns(2)->schema([
                                    self::money('price', 'Price')->required(fn () => (bool) config('shop.checkout_enabled')),
                                    self::money('compare_at_price', 'Was price')
                                        ->helperText('Shows the product as on sale')
                                        ->visible(fn () => (bool) config('shop.checkout_enabled')),
                                ]),

                            Section::make('Where it appears')->columns(2)->schema([
                                Select::make('categories')
                                    ->relationship('categories', 'name', fn ($query) => $query->with('parent.parent')->orderBy('parent_id')->orderBy('sort_order'))
                                    ->getOptionLabelFromRecordUsing(fn (Category $record) => $record->full_name)
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                                Select::make('collections')
                                    ->relationship('collections', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                                Select::make('size_guide_id')
                                    ->label('Size guide')
                                    ->relationship('sizeGuide', 'name')
                                    ->preload()
                                    ->placeholder('From category')
                                    ->helperText('Only set this if this product fits differently from its category.'),
                            ]),
                        ]),

                        Tab::make('Description')->schema([
                            Textarea::make('description')->rows(5),
                            Textarea::make('details')
                                ->rows(6)
                                ->helperText('One bullet point per line'),
                            TextInput::make('composition')
                                ->placeholder('Shell: 100% Cotton'),
                            Textarea::make('care')->rows(3),
                            Textarea::make('model_info')
                                ->label('Model / fit info')
                                ->rows(3),
                        ]),

                        Tab::make('Colours, photos & sizes')->schema([
                            Repeater::make('colours')
                                ->relationship()
                                ->orderColumn('sort_order')
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                ->addActionLabel('Add colour')
                                ->columns(3)
                                ->schema([
                                    TextInput::make('name')
                                        ->placeholder('Black')
                                        ->required(),
                                    TextInput::make('code')
                                        ->label('Colour code')
                                        ->placeholder('900')
                                        ->helperText('Used in the SKU')
                                        ->required(),
                                    ColorPicker::make('hex')->label('Swatch'),

                                    Repeater::make('images')
                                        ->label('Photos')
                                        ->relationship()
                                        ->orderColumn('sort_order')
                                        ->reorderableWithDragAndDrop()
                                        ->grid(4)
                                        ->addActionLabel('Add photo')
                                        ->columnSpanFull()
                                        ->schema([
                                            FileUpload::make('path')
                                                ->hiddenLabel()
                                                ->image()
                                                ->disk('public')
                                                ->directory('products')
                                                ->maxSize(10240)
                                                ->required(),
                                            TextInput::make('alt')
                                                ->hiddenLabel()
                                                ->placeholder('Alt text (for SEO)'),
                                        ]),

                                    Repeater::make('variants')
                                        ->label('Sizes & stock')
                                        ->relationship()
                                        ->orderColumn('sort_order')
                                        ->addActionLabel('Add size')
                                        ->columnSpanFull()
                                        ->columns(6)
                                        ->schema([
                                            TextInput::make('size')
                                                ->placeholder('M')
                                                ->required(),
                                            Select::make('length')
                                                ->options(['Regular' => 'Regular', 'Petit' => 'Petit', 'Long' => 'Long'])
                                                ->placeholder('—'),
                                            TextInput::make('stock')
                                                ->numeric()
                                                ->minValue(0)
                                                ->default(0)
                                                ->required(),
                                            self::money('price', 'Price override'),
                                            TextInput::make('sku')
                                                ->label('SKU')
                                                ->placeholder('Auto')
                                                ->distinct(),
                                            TextInput::make('barcode'),
                                        ]),
                                ]),
                        ]),

                        Tab::make('SEO')->schema([
                            TextInput::make('seo_title')->maxLength(70),
                            Textarea::make('seo_description')->maxLength(160)->rows(3),
                        ]),
                    ]),
            ]);
    }

    /** A Rand input that is stored in the database as cents. */
    public static function money(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix('R')
            ->numeric()
            ->minValue(0)
            ->formatStateUsing(fn ($state) => $state === null ? null : $state / 100)
            ->dehydrateStateUsing(fn ($state) => ($state === null || $state === '') ? null : (int) round(((float) $state) * 100));
    }
}
