<?php

namespace App\Filament\Resources\PhotoTags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PhotoTagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações da Tag de Foto')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome da Tag / Categoria')
                            ->placeholder('Ex: Cerimônia, Ensaio, Noiva, Decoração')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set) {
                                if ($operation === 'create' && $state) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->placeholder('Ex: cerimonia')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Identificador único usado nas URLs e filtros.'),
                    ])
                    ->columns(2),
            ]);
    }
}
