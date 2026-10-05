<?php

namespace App\Filament\Resources\GalleryResource\RelationManagers;

use App\Models\Gallery\Photo;
use App\Models\Gallery\PhotoTag;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Fotos da Galeria';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('filename')
                    ->label('Nome do Arquivo')
                    ->disabled(),

                Section::make('Dados da câmera')
                    ->schema([
                        TextInput::make('exif_metadata.camera_make')
                            ->label('Fabricante')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Não informado'),

                        TextInput::make('exif_metadata.camera_model')
                            ->label('Câmera')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Não informado'),

                        TextInput::make('exif_metadata.lens')
                            ->label('Lente')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Não informado'),

                        TextInput::make('exif_metadata.iso')
                            ->label('ISO')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Não informado'),

                        TextInput::make('exif_metadata.aperture')
                            ->label('Abertura')
                            ->formatStateUsing(fn (mixed $state): ?string => is_numeric($state) ? 'f/' . $state : null)
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Não informado'),

                        TextInput::make('exif_metadata.shutter_speed')
                            ->label('Velocidade do obturador')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Não informado'),

                        TextInput::make('exif_metadata.focal_length_mm')
                            ->label('Distância focal (mm)')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Não informado'),

                        TextInput::make('exif_metadata.captured_at')
                            ->label('Data da captura')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Não informado'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Select::make('rating')
                    ->label('Classificação / Nota (1 a 5 estrelas)')
                    ->options([
                        5 => '⭐⭐⭐⭐⭐ (5 - Excelente / Destaque)',
                        4 => '⭐⭐⭐⭐ (4 - Muito Boa)',
                        3 => '⭐⭐⭐ (3 - Boa / Padrão)',
                        2 => '⭐⭐ (2 - Regular)',
                        1 => '⭐ (1 - Baixa)',
                    ])
                    ->placeholder('Sem classificação')
                    ->nullable(),

                Select::make('tags')
                    ->label('Tags / Categorias')
                    ->relationship(
                        name: 'tags',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('user_id', Auth::id()),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nome da Tag')
                            ->placeholder('Ex: Cerimônia, Ensaio, Noivos')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        $tag = PhotoTag::firstOrCreate(
                            [
                                'user_id' => Auth::id(),
                                'slug' => Str::slug($data['name']),
                            ],
                            [
                                'name' => $data['name'],
                            ]
                        );

                        return $tag->id;
                    })
                    ->placeholder('Selecione ou crie novas tags')
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('Notas / Observações')
                    ->placeholder('Adicione anotações sobre esta foto...')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('filename')
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->state(fn (Photo $record): string => route('gallery.photo.admin-thumbnail', $record))
                    ->label('Foto')
                    ->square()
                    ->size(60),

                TextColumn::make('filename')
                    ->label('Nome do Arquivo')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('rating')
                    ->label('Classificação')
                    ->formatStateUsing(fn (?int $state) => $state ? str_repeat('⭐', $state) : '—')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->separator(', ')
                    ->color('primary')
                    ->searchable()
                    ->placeholder('Sem tags'),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->notes)
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('sort_order')
                    ->label('Ordem')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('rating')
                    ->label('Classificação')
                    ->options([
                        '5' => '⭐⭐⭐⭐⭐ (5 estrelas)',
                        '4' => '⭐⭐⭐⭐ (4 estrelas)',
                        '3' => '⭐⭐⭐ (3 estrelas)',
                        '2' => '⭐⭐ (2 estrelas)',
                        '1' => '⭐ (1 estrela)',
                    ]),
            ])
            ->headerActions([
                // Photos are synced from Google Drive
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Editar Foto (Classificação, Tags e Notas)'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }
}
