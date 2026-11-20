<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/** Read-only record of who changed what in the admin. Admins only. */
class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?int $navigationSort = 20;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** "App\Models\ProductVariant" → "Product variant" */
    public static function subjectLabel(?string $type): string
    {
        return $type ? ucfirst(strtolower(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename($type))))) : '—';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->label('When')->dateTime(),
            TextEntry::make('causer.name')->label('Who')->placeholder('System / visitor'),
            TextEntry::make('description')->label('What'),
            TextEntry::make('subject_type')->label('Item')
                ->formatStateUsing(fn ($state, Activity $record) => self::subjectLabel($state).' #'.$record->subject_id)
                ->placeholder('—'),
            KeyValueEntry::make('old')->label('Before')
                ->state(fn (Activity $r) => self::flatten($r->attribute_changes?->get('old') ?? $r->properties?->get('old')))
                ->visible(fn ($state) => filled($state)),
            KeyValueEntry::make('new')->label('After')
                ->state(fn (Activity $r) => self::flatten($r->attribute_changes?->get('attributes') ?? $r->properties?->get('attributes')))
                ->visible(fn ($state) => filled($state)),
            KeyValueEntry::make('extra')->label('Details')->columnSpanFull()
                ->state(fn (Activity $r) => self::flatten($r->properties?->except(['old', 'attributes'])->all()))
                ->visible(fn ($state) => filled($state)),
        ]);
    }

    /** Make nested values readable in the key/value lists. */
    private static function flatten(?array $values): array
    {
        return collect($values ?? [])->map(function ($v) {
            $text = is_scalar($v) || $v === null ? var_export($v, true) : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $text = trim($text, "'");

            return mb_strlen($text) > 300 ? mb_substr($text, 0, 300).'…' : $text;
        })->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('causer'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->since()->sortable()
                    ->tooltip(fn (Activity $r) => $r->created_at?->timezone(config('app.admin_timezone'))->format('j M Y H:i')),
                TextColumn::make('causer.name')->label('Who')->placeholder('—')->searchable(),
                TextColumn::make('description')->label('What')->searchable(),
                TextColumn::make('subject_type')->label('Item')
                    ->formatStateUsing(fn ($state, Activity $r) => self::subjectLabel($state).' #'.$r->subject_id)
                    ->placeholder('—'),
                TextColumn::make('log_name')->label('Area')->badge()->color('gray'),
            ])
            ->filters([
                SelectFilter::make('log_name')->label('Area')->options([
                    'default' => 'Catalogue & content', 'settings' => 'Settings', 'auth' => 'Sign-ins',
                ]),
                SelectFilter::make('causer_id')->label('Who')
                    ->options(fn () => \App\Models\User::team()->pluck('name', 'id')),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityLogs::route('/')];
    }
}
