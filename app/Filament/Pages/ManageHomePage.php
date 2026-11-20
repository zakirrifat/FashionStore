<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Build the shop's home page from blocks: banners, video, product rows, tiles and text.
 * Saved as the "home_sections" setting.
 */
class ManageHomePage extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Home page';

    protected static ?string $title = 'Home page';

    protected static ?string $slug = 'home-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['sections' => Setting::get('home_sections') ?? []]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('View home page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(route('shop.home'))
                ->openUrlInNewTab(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Builder::make('sections')
                    ->hiddenLabel()
                    ->addActionLabel('Add section')
                    ->collapsible()
                    ->cloneable()
                    ->blockNumbers(false)
                    ->blockPickerColumns(2)
                    ->helperText('Sections appear on the home page in this order. Drag to reorder. Leave empty to show the automatic home page (latest featured product + new arrivals).')
                    ->blocks([
                        $this->heroBlock(),
                        $this->doubleBannerBlock(),
                        $this->productsBlock(),
                        $this->tilesBlock(),
                        $this->textBlock(),
                    ]),
            ]);
    }

    private function heroBlock(): Block
    {
        return Block::make('hero')
            ->label(fn (?array $state) => 'Large banner'.(filled($state['heading'] ?? null) ? ' â€” '.$state['heading'] : ''))
            ->icon('heroicon-o-photo')
            ->columns(2)
            ->schema([
                Radio::make('media')
                    ->options(['image' => 'Image', 'video' => 'Video'])
                    ->default('image')
                    ->inline()
                    ->live()
                    ->columnSpanFull(),
                self::image('image', 'Image (desktop)')
                    ->required(fn (Get $get) => $get('media') !== 'video')
                    ->helperText('Landscape, at least 2400 px wide.'),
                self::image('mobile_image', 'Image for phones (optional)')
                    ->helperText('Portrait. If empty, the desktop image is used.'),
                FileUpload::make('video')
                    ->label('Video (MP4, max 50 MB)')
                    ->disk('public')
                    ->directory('home')
                    ->acceptedFileTypes(['video/mp4', 'video/webm'])
                    ->maxSize(51200)
                    ->visible(fn (Get $get) => $get('media') === 'video')
                    ->required(fn (Get $get) => $get('media') === 'video')
                    ->helperText('Plays muted and on loop. The image above is shown while it loads.'),
                TextInput::make('eyebrow')->label('Small text above heading')->placeholder('New season'),
                TextInput::make('heading')->placeholder('Winter Collection'),
                TextInput::make('button_text')->placeholder('Shop now'),
                self::link('link'),
                Select::make('text_colour')->options(['light' => 'White text', 'dark' => 'Black text'])->default('light')->required(),
                Select::make('height')->options(['full' => 'Full screen', 'large' => 'Large (70%)', 'medium' => 'Medium (50%)'])->default('full')->required(),
            ]);
    }

    private function doubleBannerBlock(): Block
    {
        $half = fn (string $key, string $label) => Fieldset::make($label)->columns(1)->schema([
            self::image("{$key}_image", 'Image')->required()->helperText('Portrait works best.'),
            TextInput::make("{$key}_title")->label('Title')->required(),
            self::link("{$key}_link")->required(),
        ]);

        return Block::make('double_banner')
            ->label(fn (?array $state) => 'Two banners'.(filled($state['left_title'] ?? null) ? ' â€” '.$state['left_title'].' / '.($state['right_title'] ?? '') : ''))
            ->icon('heroicon-o-view-columns')
            ->columns(2)
            ->schema([
                $half('left', 'Left'),
                $half('right', 'Right'),
            ]);
    }

    private function productsBlock(): Block
    {
        return Block::make('products')
            ->label(fn (?array $state) => 'Product row'.(filled($state['title'] ?? null) ? ' â€” '.$state['title'] : ''))
            ->icon('heroicon-o-squares-2x2')
            ->columns(2)
            ->schema([
                TextInput::make('title')->required()->placeholder('New arrivals'),
                Select::make('source')
                    ->label('Show')
                    ->options([
                        'latest' => 'Newest products',
                        'featured' => 'Featured products',
                        'sale' => 'Products on sale',
                        'collection' => 'A collectionâ€¦',
                        'category' => 'A categoryâ€¦',
                    ])
                    ->default('latest')
                    ->required()
                    ->live(),
                Select::make('collection_id')
                    ->label('Collection')
                    ->options(fn () => Collection::orderBy('sort_order')->pluck('name', 'id'))
                    ->visible(fn (Get $get) => $get('source') === 'collection')
                    ->required(fn (Get $get) => $get('source') === 'collection'),
                Select::make('category_id')
                    ->label('Category')
                    ->options(fn () => Category::with('parent.parent')->get()->mapWithKeys(fn ($c) => [$c->id => $c->full_name])->sort())
                    ->searchable()
                    ->visible(fn (Get $get) => $get('source') === 'category')
                    ->required(fn (Get $get) => $get('source') === 'category'),
                Select::make('limit')->label('How many')->options([4 => 4, 8 => 8, 12 => 12])->default(4)->required(),
                TextInput::make('link_text')->label('Link text')->placeholder('Shop all'),
                self::link('link')->helperText('Leave empty to link to the chosen collection or category automatically.'),
            ]);
    }

    private function tilesBlock(): Block
    {
        return Block::make('tiles')
            ->label(fn (?array $state) => 'Image tiles'.(filled($state['title'] ?? null) ? ' â€” '.$state['title'] : ''))
            ->icon('heroicon-o-rectangle-group')
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('title')->placeholder('Discover more'),
                    Select::make('columns')->options([2 => '2 per row', 3 => '3 per row', 4 => '4 per row'])->default(4)->required(),
                ]),
                Repeater::make('tiles')
                    ->hiddenLabel()
                    ->addActionLabel('Add tile')
                    ->grid(4)
                    ->minItems(1)
                    ->maxItems(12)
                    ->reorderableWithDragAndDrop()
                    ->collapsible()
                    ->itemLabel(fn (array $state) => $state['title'] ?? null)
                    ->schema([
                        self::image('image', 'Image')->required(),
                        TextInput::make('title')->required(),
                        self::link('link')->required(),
                    ]),
            ]);
    }

    private function textBlock(): Block
    {
        return Block::make('text')
            ->label(fn (?array $state) => 'Text'.(filled($state['heading'] ?? null) ? ' â€” '.$state['heading'] : ''))
            ->icon('heroicon-o-bars-3-bottom-left')
            ->columns(2)
            ->schema([
                TextInput::make('heading'),
                Select::make('align')->options(['center' => 'Centred', 'left' => 'Left'])->default('center')->required(),
                Textarea::make('body')->rows(4)->columnSpanFull(),
                TextInput::make('button_text'),
                self::link('link'),
            ]);
    }

    private static function image(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->disk('public')
            ->directory('home')
            ->maxSize(20480);
    }

    /** A link: a page on this site ("/c/woman") or a full web address. */
    private static function link(string $name): TextInput
    {
        return TextInput::make($name)
            ->label('Link')
            ->placeholder('/c/woman  or  https://â€¦')
            ->regex('#^(/[^\s]*|https?://[^\s]+)$#')
            ->validationMessages(['regex' => 'Start with / for a page on this site, or https:// for another site.']);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save home page')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $sections = array_values($this->form->getState()['sections'] ?? []);

        Setting::saveMany(['home_sections' => $sections], 'Home page updated');

        Notification::make()->success()->title('Home page saved')->send();
    }}
