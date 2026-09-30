<?php

namespace App\Filament\Resources;

use App\Actions\Gallery\SyncGalleryFromDrive;
use App\Filament\Resources\GalleryResource\Pages;
use App\Models\Gallery\Gallery;
use App\Services\Google\GoogleDriveService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
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
use Illuminate\Validation\Rule;

class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 1;

    /*
    |--------------------------------------------------------------------------
    | Resource definition
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereBelongsTo(Auth::user());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGalleries::route('/'),
            'create' => Pages\CreateGallery::route('/create'),
            'edit' => Pages\EditGallery::route('/{record}/edit'),
            'photos' => Pages\ManagePhotos::route('/{record}/photos'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Group::make([
                            self::detailsSection(),
                            self::driveSection(),
                        ])->columnSpan(['lg' => 2]),

                        Group::make([
                            self::publishingSection(),
                            self::accessSection(),
                        ])->columnSpan(['lg' => 1]),
                    ]),
            ]);
    }

    private static function detailsSection(): Section
    {
        return Section::make('Gallery Details')
            ->description('Name, public URL and description.')
            ->icon('heroicon-o-photo')
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(
                        fn(string $operation, ?string $state, Set $set) => $operation === 'create'
                            ? $set('slug', Str::slug($state ?? ''))
                            : null
                    ),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->prefix('/g/')
                    ->unique(Gallery::class, 'slug', ignoreRecord: true)
                    ->helperText('Used in the public URL.'),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    private static function driveSection(): Section
    {
        return Section::make('Google Drive')
            ->description('Folder that feeds this gallery.')
            ->icon('heroicon-o-folder-open')
            ->columns(2)
            ->schema([
                self::driveFolderSelect()
                    ->columnSpanFull(),

                TextInput::make('drive_folder_name')
                    ->label('Folder Name')
                    ->placeholder('Optional friendly name')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    private static function publishingSection(): Section
    {
        return Section::make('Publishing')
            ->icon('heroicon-o-globe-alt')
            ->schema([
                Toggle::make('is_published')
                    ->label('Published')
                    ->helperText('When disabled, the gallery URL will return a 404.'),
            ]);
    }

    private static function accessSection(): Section
    {
        return Section::make('Access')
            ->description('Who can view this gallery.')
            ->icon('heroicon-o-lock-closed')
            ->schema([
                Select::make('access_type')
                    ->options(self::accessLabels())
                    ->default(Gallery::ACCESS_PUBLIC)
                    ->required()
                    ->native(false)
                    ->live(),

                self::clientSelect(),
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
            ->unique(Gallery::class, 'drive_folder_id', ignoreRecord: true)
            ->validationMessages([
                'unique' => 'Esta pasta do Google Drive já está vinculada a outra galeria.',
            ])
            ->getSearchResultsUsing(
                fn(string $search, ?Gallery $record): array => self::searchDriveFolders($search, $record)
            )
            ->getOptionLabelUsing(fn(?string $value): ?string => self::getDriveFolderName($value));
    }

    private static function clientSelect(): Select
    {
        return Select::make('client_id')
            ->relationship(
                name: 'client',
                titleAttribute: 'name',
                modifyQueryUsing: fn(Builder $query) => $query->whereBelongsTo(Auth::user()),
            )
            ->label('Client')
            ->placeholder('Select a client...')
            ->searchable()
            ->preload()
            ->nullable()
            ->required(fn(Get $get): bool => self::isPrivate($get))
            ->rules(fn(Get $get): array => self::isPrivate($get)
                ? [Rule::exists('clients', 'id')->where(
                    fn($query) => $query->where('user_id', Auth::id())->whereNotNull('email')->where('email', '!=', '')
                )]
                : [])
            ->helperText(fn(Get $get): string => self::isPrivate($get)
                ? 'Private access uses this client’s email address.'
                : 'Optional.');
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                self::nameColumn(),
                self::statusColumn(),
                self::accessColumn(),
                self::photosColumn(),
                self::clientColumn(),
                self::driveFolderColumn(),
                self::lastSyncColumn(),
                self::createdAtColumn(),
            ])
            ->filters([
                self::statusFilter(),
                self::accessFilter(),
                self::clientFilter(),
            ])
            ->recordActions([
                self::photosAction(),
                self::syncAction(),
                ActionGroup::make([
                    self::viewAction(),
                    self::copyUrlAction(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('More actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->emptyStateIcon('heroicon-o-photo')
            ->emptyStateHeading('No galleries yet')
            ->emptyStateDescription('Create a gallery and link it to a Google Drive folder.');
    }

    private static function nameColumn(): TextColumn
    {
        return TextColumn::make('name')
            ->searchable()
            ->sortable()
            ->weight('semibold')
            ->description(fn(Gallery $record): string => '/g/' . $record->slug)
            ->wrap();
    }

    private static function statusColumn(): TextColumn
    {
        return TextColumn::make('is_published')
            ->label('Status')
            ->badge()
            ->sortable()
            ->formatStateUsing(fn(bool $state): string => $state ? 'Published' : 'Draft')
            ->icon(fn(bool $state): string => $state ? 'heroicon-m-check-circle' : 'heroicon-m-pencil-square')
            ->color(fn(bool $state): string => $state ? 'success' : 'gray');
    }

    private static function accessColumn(): TextColumn
    {
        return TextColumn::make('access_type')
            ->label('Access')
            ->badge()
            ->sortable()
            ->formatStateUsing(
                fn(string $state): string => match ($state) {
                    Gallery::ACCESS_LINK => 'Link only',
                    Gallery::ACCESS_PRIVATE => 'Private',
                    default => 'Public',
                }
            )
            ->icon(
                fn(string $state): string => match ($state) {
                    Gallery::ACCESS_LINK => 'heroicon-m-link',
                    Gallery::ACCESS_PRIVATE => 'heroicon-m-lock-closed',
                    default => 'heroicon-m-globe-alt',
                }
            )
            ->color(
                fn(string $state): string => match ($state) {
                    Gallery::ACCESS_LINK => 'gray',
                    Gallery::ACCESS_PRIVATE => 'warning',
                    default => 'info',
                }
            );
    }

    private static function photosColumn(): TextColumn
    {
        return TextColumn::make('photos_count')
            ->label('Photos')
            ->counts('photos')
            ->badge()
            ->color('gray')
            ->icon('heroicon-m-photo')
            ->sortable()
            ->alignEnd();
    }

    private static function clientColumn(): TextColumn
    {
        return TextColumn::make('client.name')
            ->label('Client')
            ->placeholder('—')
            ->searchable()
            ->toggleable();
    }

    private static function driveFolderColumn(): TextColumn
    {
        return TextColumn::make('drive_folder_name')
            ->label('Drive Folder')
            ->placeholder('—')
            ->icon('heroicon-m-folder')
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    private static function lastSyncColumn(): TextColumn
    {
        return TextColumn::make('last_synced_at')
            ->label('Last Sync')
            ->since()
            ->tooltip(fn(Gallery $record): ?string => $record->last_synced_at?->format('d/m/Y H:i'))
            ->sortable()
            ->placeholder('Never')
            ->toggleable();
    }

    private static function createdAtColumn(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label('Created')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    private static function statusFilter(): SelectFilter
    {
        return SelectFilter::make('is_published')
            ->label('Status')
            ->options([
                '1' => 'Published',
                '0' => 'Draft',
            ]);
    }

    private static function accessFilter(): SelectFilter
    {
        return SelectFilter::make('access_type')
            ->label('Access')
            ->options(self::accessLabels());
    }

    private static function clientFilter(): SelectFilter
    {
        return SelectFilter::make('client_id')
            ->label('Client')
            ->relationship(
                name: 'client',
                titleAttribute: 'name',
                modifyQueryUsing: fn(Builder $query) => $query->whereBelongsTo(Auth::user()),
            )
            ->searchable()
            ->preload();
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    private static function photosAction(): Action
    {
        return Action::make('photos')
            ->button()
            ->size(Size::ExtraSmall)
            ->color('gray')
            ->label('Photos')
            ->icon('heroicon-o-photo')
            ->url(fn(Gallery $record): string => self::getUrl('photos', ['record' => $record]));
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
                fn(Gallery $record): bool => filled($record->drive_folder_id)
            )
            ->action(fn(Gallery $record) => self::runSync($record));
    }

    public static function runSync(Gallery $gallery): void
    {
        try {
            $result = app(SyncGalleryFromDrive::class)
                ->handle($gallery);

            Notification::make()
                ->title(
                    "Sync complete: {$result->added} added, " .
                        "{$result->updated} updated, " .
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
    }

    private static function viewAction(): Action
    {
        return Action::make('view_public')
            ->label('View Gallery')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->url(fn(Gallery $record): string => route('gallery.show', $record->slug))
            ->openUrlInNewTab()
            ->disabled(fn(Gallery $record): bool => ! $record->is_published);
    }

    private static function copyUrlAction(): Action
    {
        return Action::make('copy_url')
            ->label('Copy URL')
            ->icon('heroicon-o-link')
            ->disabled(fn(Gallery $record): bool => ! $record->is_published)
            ->action(function (Gallery $record, Action $action): void {
                $url = route('gallery.show', $record->slug);

                $action->getLivewire()->js(
                    'navigator.clipboard.writeText(' .
                        json_encode($url) .
                        ')'
                );

                Notification::make()
                    ->title('URL copied')
                    ->success()
                    ->send();
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Google Drive helpers
    |--------------------------------------------------------------------------
    */

    private static function searchDriveFolders(string $search, ?Gallery $record = null): array
    {
        $user = Auth::user();

        if (! $user || blank($search)) {
            return [];
        }

        $folders = app(GoogleDriveService::class)->searchFolders($user, $search);
        $folderIds = collect($folders)->pluck('id')->all();

        $usedFolderIds = Gallery::query()
            ->when($record, fn(Builder $query) => $query->whereKeyNot($record->getKey()))
            ->whereIn('drive_folder_id', $folderIds)
            ->pluck('drive_folder_id')
            ->all();

        return collect($folders)
            ->reject(fn($folder): bool => in_array($folder->id, $usedFolderIds, true))
            ->mapWithKeys(fn($folder) => [$folder->id => $folder->displayName])
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

    /*
    |--------------------------------------------------------------------------
    | Shared helpers
    |--------------------------------------------------------------------------
    */

    private static function accessLabels(): array
    {
        return [
            Gallery::ACCESS_PUBLIC => 'Public — listed and searchable',
            Gallery::ACCESS_LINK => 'Restricted — direct link only',
            Gallery::ACCESS_PRIVATE => 'Private — access code or authorized email',
        ];
    }

    private static function isPrivate(Get $get): bool
    {
        return $get('access_type') === Gallery::ACCESS_PRIVATE;
    }
}
