<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Services\ProductCsv;
use App\Support\Csv;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('import')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalDescription('Add or update products, colours, sizes, prices and stock from a spreadsheet saved as CSV. If any row has a problem, nothing is changed and you get a list of what to fix. Photos are added in the product editor.')
                    ->modalSubmitActionLabel('Import')
                    ->schema([
                        FileUpload::make('file')
                            ->label('CSV file')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->maxSize(10240)
                            ->storeFiles(false)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        /** @var TemporaryUploadedFile $file */
                        $file = $data['file'];
                        $result = ProductCsv::import(Csv::read($file->getRealPath()));

                        if (! $result['ok']) {
                            $list = collect($result['errors']);
                            Notification::make()
                                ->danger()
                                ->title('Nothing was imported — please fix '.$list->count().' '.Str::plural('problem', $list->count()))
                                ->body($list->take(15)->map(fn ($e) => '• '.e($e))->implode('<br>').($list->count() > 15 ? '<br>… and '.($list->count() - 15).' more' : ''))
                                ->persistent()
                                ->send();

                            return;
                        }

                        $s = $result['summary'];
                        Notification::make()
                            ->success()
                            ->title('Import complete')
                            ->body("Products: {$s['products_created']} new, {$s['products_updated']} updated. Sizes: {$s['sizes_created']} new, {$s['sizes_updated']} updated. New products are saved as Draft.")
                            ->persistent()
                            ->send();
                    }),
                Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => Csv::download('products-'.now()->format('Y-m-d').'.csv', ProductCsv::COLUMNS, ProductCsv::exportRows())),
                Action::make('template')
                    ->label('Download template')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn () => Csv::download('product-import-template.csv', ProductCsv::COLUMNS, ProductCsv::templateRows())),
            ])
                ->label('Spreadsheet')
                ->icon('heroicon-o-table-cells')
                ->button()
                ->color('gray'),
            CreateAction::make(),
        ];
    }
}
