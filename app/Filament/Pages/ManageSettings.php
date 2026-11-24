<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Brand;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Store settings';

    protected static ?string $title = 'Store settings';

    public ?array $data = [];

    /** Only admins can change store settings. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $values = Setting::allValues();
        // Stored as a plain list of strings; the repeater edits [['text' => …], …].
        $values['product_notes'] = collect($values['product_notes'] ?? [])->map(fn ($t) => ['text' => $t])->all();

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Section::make('Brand')
                    ->description('Upload your logo once — the site makes a dark version, a white version (for photos and the footer), browser/phone icons and a share image automatically. Without a logo, the store name is shown as text.')
                    ->columns(3)
                    ->schema([
                        FileUpload::make('brand_logo')
                            ->label('Logo')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->disk('public')
                            ->directory('brand/uploads')
                            ->maxSize(10240)
                            ->helperText('PNG or JPG, at least 1000 px wide. A dark logo on white or transparent works best.'),
                        FileUpload::make('brand_icon')
                            ->label('Square icon (optional)')
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->disk('public')
                            ->directory('brand/uploads')
                            ->maxSize(5120)
                            ->helperText('For the browser tab and phone home screen, 512×512 px. If empty, made from the logo.'),
                        Grid::make(1)->schema([
                            Toggle::make('brand_remove_bg')
                                ->label('Remove white background and use one colour')
                                ->default(true)
                                ->helperText('Turn off only for a multi-colour logo that already has a transparent background.'),
                            ViewField::make('brand_preview')->view('filament.brand-preview'),
                        ]),
                    ]),

                Section::make('Announcement bar')
                    ->description('Thin bar above the header on every page.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('announcement_enabled')->label('Show announcement bar')->columnSpanFull(),
                        TextInput::make('announcement_text')
                            ->placeholder('Free delivery on orders over R 1 500')
                            ->maxLength(150)
                            ->required(fn (Get $get) => (bool) $get('announcement_enabled')),
                        TextInput::make('announcement_link')
                            ->label('Link (optional)')
                            ->placeholder('/c/woman or https://…'),
                    ]),

                Section::make('Contact details')
                    ->description('Shown on the Contact page and footer. Contact form messages are emailed here.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_email')->email()->required(),
                        TextInput::make('contact_phone')->tel(),
                        TextInput::make('contact_hours')->label('Opening hours'),
                        Textarea::make('contact_address')->label('Address')->rows(3),
                    ]),

                Section::make('Product page')
                    ->columns(1)
                    ->schema([
                        Repeater::make('product_notes')
                            ->label('Notes under “Ask for price”')
                            ->schema([TextInput::make('text')->hiddenLabel()->required()->maxLength(120)])
                            ->addActionLabel('Add note')
                            ->reorderable(),
                        Textarea::make('shipping_returns')
                            ->label('Shipping & returns summary')
                            ->rows(3),
                    ]),

                Section::make('Checkout & payment')
                    ->description('Orders are paid by bank transfer, arranged by your team after the order is placed.')
                    ->visible(fn () => config('shop.checkout_enabled'))
                    ->columns(2)
                    ->schema([
                        Textarea::make('payment_instructions')
                            ->label('Payment instructions shown to the customer after ordering')
                            ->rows(5)
                            ->placeholder("Bank: …\nAccount name: …\nAccount number: …\nBranch code: …\nReference: your order number")
                            ->helperText('Shown on the order confirmation page and in the confirmation email. Leave empty to just say you will contact them.')
                            ->columnSpanFull(),
                        TextInput::make('order_prefix')->label('Order number prefix')->maxLength(4)->alpha()->required()
                            ->helperText('Orders are numbered like OMR-100001.'),
                        TextInput::make('vat_rate')->label('VAT rate (%)')->numeric()->integer()->minValue(0)->maxValue(30)->required(),
                        Toggle::make('vat_free_exports')->label('Remove VAT for orders shipped outside South Africa')->inline(false),
                        TextInput::make('gift_wrap_price')->label('Gift wrapping price')->prefix('R')->numeric()->minValue(0)
                            ->helperText('0 = free')
                            ->formatStateUsing(fn ($state) => $state === null ? 0 : $state / 100)
                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100)),
                    ]),

                Section::make('Social media')
                    ->description('Full links. Leave empty to hide.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('social_instagram')->label('Instagram')->url(),
                        TextInput::make('social_facebook')->label('Facebook')->url(),
                        TextInput::make('social_tiktok')->label('TikTok')->url(),
                        TextInput::make('social_x')->label('X / Twitter')->url(),
                        TextInput::make('social_youtube')->label('YouTube')->url(),
                        TextInput::make('social_pinterest')->label('Pinterest')->url(),
                    ]),

                Section::make('General')
                    ->columns(2)
                    ->schema([
                        TextInput::make('country_label')->label('Country / currency label')->placeholder('South Africa (ZAR)'),
                        TextInput::make('low_stock_threshold')->label('Low-stock alert at (units per size)')
                            ->numeric()->integer()->minValue(0)->maxValue(100)->required(),
                        Textarea::make('seo_description')->label('Default search-engine description')->maxLength(160)->rows(2),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save settings')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['product_notes'] = collect($data['product_notes'] ?? [])
            ->map(fn ($row) => is_array($row) ? ($row['text'] ?? '') : $row)
            ->filter()->values()->all();

        unset($data['brand_preview']);

        // Logo changed → (re)make the dark/white logo, icons and share image.
        $before = Setting::allValues();
        $brandChanged = ($data['brand_logo'] ?? null) !== ($before['brand_logo'] ?? null)
            || ($data['brand_icon'] ?? null) !== ($before['brand_icon'] ?? null)
            || (bool) ($data['brand_remove_bg'] ?? true) !== (bool) ($before['brand_remove_bg'] ?? true);
        if ($brandChanged) {
            $generated = ['brand_logo_dark' => null, 'brand_logo_light' => null, 'brand_icon_512' => null, 'brand_icon_180' => null, 'brand_icon_32' => null, 'brand_og' => null];
            if (filled($data['brand_logo'] ?? null)) {
                try {
                    $generated = Brand::process($data['brand_logo'], (bool) ($data['brand_remove_bg'] ?? true), $data['brand_icon'] ?? null);
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('The logo could not be processed')->body($e->getMessage())->send();

                    return;
                }
            }
            $data += $generated;
            Brand::cleanup(array_values(array_filter($generated)));
        }

        Setting::saveMany($data, 'Store settings updated');

        Notification::make()->success()->title($brandChanged ? 'Settings saved — logo and icons updated' : 'Settings saved')->send();
    }
}
