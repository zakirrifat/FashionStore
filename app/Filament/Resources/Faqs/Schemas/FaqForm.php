<?php

namespace App\Filament\Resources\Faqs\Schemas;

use App\Models\Faq;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()->columns(3)->schema([
                    Select::make('topic')
                        ->options(array_combine(Faq::TOPICS, Faq::TOPICS))
                        ->default('General')
                        ->required(),
                    TextInput::make('sort_order')->numeric()->default(0),
                    Toggle::make('is_active')->label('Published')->default(true)->inline(false),
                    TextInput::make('question')->required()->columnSpanFull(),
                    RichEditor::make('answer')
                        ->required()
                        ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList']])
                        ->columnSpanFull(),
                ]),
            ]);
    }
}
