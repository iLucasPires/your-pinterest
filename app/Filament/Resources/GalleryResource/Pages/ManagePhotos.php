<?php

namespace App\Filament\Resources\GalleryResource\Pages;

use App\Filament\Resources\GalleryResource;
use App\Models\Gallery\Photo;
use App\Models\Gallery\PhotoTag;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ManagePhotos extends ManageRelatedRecords
{
    protected static string $resource = GalleryResource::class;

    protected static string $relationship = 'photos';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $title = 'Fotos da Galeria';

    public static function getNavigationLabel(): string
    {
        return 'Fotos';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sync Drive')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => filled($this->getRecord()->drive_folder_id))
                ->action(fn () => GalleryResource::runSync($this->getRecord())),

            Action::make('back')
                ->label('Voltar para a galeria')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => GalleryResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('filename')
                    ->label('Nome do Arquivo')
                    ->disabled(),

                Select::make('rating')
                    ->label('Classificação / Nota (1 a 5 estrelas)')
                    ->options(self::ratingOptions())
                    ->placeholder('Sem classificação')
                    ->nullable(),

                self::tagsSelect(),

                Textarea::make('notes')
                    ->label('Notas / Observações')
                    ->placeholder('Adicione anotações sobre esta foto...')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    private static function tagsSelect(): Select
    {
        return Select::make('tags')
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
            ->columnSpanFull();
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('filename')
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label('Foto')
                    ->state(fn (Photo $record): string => route('gallery.photo.admin-thumbnail', $record))
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
                    ->button()
                    ->size(Size::ExtraSmall)
                    ->modalHeading('Editar Foto (Classificação, Tags e Notas)'),
                DeleteAction::make()
                    ->button()
                    ->size(Size::ExtraSmall),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private static function ratingOptions(): array
    {
        return [
            5 => '⭐⭐⭐⭐⭐ (5 - Excelente / Destaque)',
            4 => '⭐⭐⭐⭐ (4 - Muito Boa)',
            3 => '⭐⭐⭐ (3 - Boa / Padrão)',
            2 => '⭐⭐ (2 - Regular)',
            1 => '⭐ (1 - Baixa)',
        ];
    }
}
