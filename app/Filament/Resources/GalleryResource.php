<?php

namespace App\Filament\Resources;

use App\Actions\Gallery\SyncGalleryFromDrive;
use App\Filament\Resources\GalleryResource\Pages;
use App\Filament\Resources\GalleryResource\RelationManagers;
use App\Models\Gallery\Gallery;
use App\Services\Google\GoogleDriveService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereBelongsTo(Auth::user());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::galleryDetailsSection(),
                self::driveSection(),
                self::accessSection(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                self::nameColumn(),
                self::statusColumn(),
                self::accessColumn(),
                self::photosColumn(),
                self::lastSyncColumn(),
                self::clientColumn(),
            ])
            ->filters([
                self::statusFilter(),
            ])
            ->recordActions([
                self::syncAction(),
                self::viewAction(),
                self::copyUrlAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGalleries::route('/'),
            'create' => Pages\CreateGallery::route('/create'),
            'edit' => Pages\EditGallery::route('/{record}/edit'),
        ];
    }

    private static function galleryDetailsSection(): Section
    {
        return Section::make('Gallery Details')
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(
                        fn (string $operation, string $state, Set $set) => $operation === 'create'
                                ? $set('slug', Str::slug($state))
                                : null
                    ),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        Gallery::class,
                        'slug',
                        ignoreRecord: true,
                    )
                    ->helperText('Used in the public URL: /g/your-slug'),

                Textarea::make('description')
                    ->rows(3),
            ]);
    }

    private static function driveSection(): Section
    {
        return Section::make('Google Drive')
            ->schema([
                self::driveFolderSelect(),

                TextInput::make('drive_folder_name')
                    ->label('Folder Name')
                    ->placeholder('Optional friendly name')
                    ->maxLength(255),
            ]);
    }

    private static function driveFolderSelect(): Select
    {
        return Select::make('drive_folder_id')
            ->label('Google Drive Folder')
            ->placeholder('Search for a folder...')
            ->searchable()
            ->searchDebounce(500)
            ->required()
            ->exists(false)
            ->getSearchResultsUsing(
                fn (string $search): array => self::searchDriveFolders($search)
            )
            ->getOptionLabelUsing(
                fn (?string $value): ?string => self::getDriveFolderName($value)
            );
    }

    private static function searchDriveFolders(string $search): array
    {
        $user = Auth::user();

        if (! $user || blank($search)) {
            return [];
        }

        return collect(
            app(GoogleDriveService::class)
                ->searchFolders($user, $search)
        )
            ->mapWithKeys(fn ($folder) => [
                $folder->id => $folder->displayName,
            ])
            ->all();
    }

    private static function getDriveFolderName(?string $folderId): ?string
    {
        if (! $folderId || ! ($user = Auth::user())) {
            return null;
        }

        return app(GoogleDriveService::class)
            ->getFolder($user, $folderId)
            ?->name;
    }

    private static function accessSection(): Section
    {
        return Section::make('Access')
            ->schema([
                Select::make('access_type')
                    ->options([
                        Gallery::ACCESS_PUBLIC => 'Public — anyone with the link',
                        Gallery::ACCESS_CODE => 'Protected — 6-digit access code',
                    ])
                    ->default(Gallery::ACCESS_PUBLIC)
                    ->required()
                    ->live(),

                TextInput::make('access_code_plain')
                    ->label('Access Code')
                    ->placeholder('e.g. 482931')
                    ->numeric()
                    ->minLength(6)
                    ->maxLength(6)
                    ->helperText('Leave blank to auto-generate.')
                    ->visible(
                        fn (Get $get): bool => $get('access_type') === Gallery::ACCESS_CODE
                    )
                    ->dehydrated(false),

                Select::make('client_id')
                    ->relationship(
                        name: 'client',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->whereBelongsTo(Auth::user()),
                    )
                    ->label('Client')
                    ->placeholder('Select a client...')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Optional.'),

                Toggle::make('is_published')
                    ->label('Published')
                    ->helperText(
                        'When disabled, the gallery URL will return a 404.'
                    ),
            ]);
    }

    private static function nameColumn(): TextColumn
    {
        return TextColumn::make('name')
            ->searchable()
            ->sortable()
            ->weight('semibold');
    }

    private static function statusColumn(): TextColumn
    {
        return TextColumn::make('is_published')
            ->label('Status')
            ->badge()
            ->formatStateUsing(
                fn (bool $state): string => $state ? 'Published' : 'Draft'
            )
            ->color(
                fn (bool $state): string => $state ? 'success' : 'gray'
            );
    }

    private static function accessColumn(): TextColumn
    {
        return TextColumn::make('access_type')
            ->label('Access')
            ->badge()
            ->formatStateUsing(
                fn (string $state): string => match ($state) {
                    Gallery::ACCESS_CODE => 'Code Protected',
                    default => 'Public',
                }
            )
            ->color(
                fn (string $state): string => match ($state) {
                    Gallery::ACCESS_CODE => 'warning',
                    default => 'info',
                }
            );
    }

    private static function photosColumn(): TextColumn
    {
        return TextColumn::make('photos_count')
            ->label('Photos')
            ->counts('photos')
            ->sortable();
    }

    private static function lastSyncColumn(): TextColumn
    {
        return TextColumn::make('last_synced_at')
            ->label('Last Sync')
            ->dateTime()
            ->sortable()
            ->placeholder('Never');
    }

    private static function clientColumn(): TextColumn
    {
        return TextColumn::make('client.name')
            ->label('Client')
            ->placeholder('—')
            ->searchable();
    }

    private static function statusFilter(): SelectFilter
    {
        return SelectFilter::make('is_published')
            ->label('Status')
            ->options([
                '1' => 'Published',
                '0' => 'Draft',
            ]);
    }

    private static function syncAction(): Action
    {
        return Action::make('sync')
            ->button()
            ->size(Size::ExtraSmall)
            ->color('warning')
            ->label('Sync Drive')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->visible(
                fn (Gallery $record): bool => filled($record->drive_folder_id)
            )
            ->action(function (Gallery $record): void {
                try {
                    $result = app(SyncGalleryFromDrive::class)
                        ->handle($record);

                    Notification::make()
                        ->title(
                            "Sync complete: {$result->added} added, ".
                            "{$result->updated} updated, ".
                            "{$result->removed} removed."
                        )
                        ->success()
                        ->send();
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->title('Sync failed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private static function viewAction(): Action
    {
        return Action::make('view_public')
            ->button()
            ->size(Size::ExtraSmall)
            ->color('gray')
            ->label('View Gallery')
            ->icon('heroicon-o-arrow-top-right-on-square')

            ->url(
                fn (Gallery $record): string => route('gallery.show', $record->slug)
            )
            ->openUrlInNewTab()
            ->disabled(
                fn (Gallery $record): bool => !$record->is_published
            );
    }

    private static function copyUrlAction(): Action
    {
        return Action::make('copy_url')
            ->button()
            ->size(Size::ExtraSmall)
            ->color('gray')
            ->label('Copy URL')
            ->icon('heroicon-o-link')
            ->disabled(
                fn (Gallery $record): bool => !$record->is_published
            )
            ->action(function (Gallery $record, Action $action): void {
                $url = route('gallery.show', $record->slug);

                $action->getLivewire()->js(
                    'navigator.clipboard.writeText('.
                    json_encode($url).
                    ')'
                );

                Notification::make()
                    ->title('URL copied')
                    ->success()
                    ->send();
            });
    }
}
