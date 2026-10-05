<?php

namespace App\Livewire\Gallery;

use App\Models\Gallery\Gallery as GalleryModel;
use App\Models\Gallery\Photo;
use App\Models\Gallery\PhotoTag;
use App\Services\Gallery\GalleryAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Viewer extends Component
{
    #[Locked]
    public int $galleryId;

    #[Url(as: 'q', except: '')]
    #[Validate('string|max:255')]
    public string $search = '';

    public function mount(int $galleryId): void
    {
        $this->galleryId = $galleryId;

        // Força a resolução da galeria (404 se não publicada).
        $this->gallery;
    }

    public function updatedSearch(): void
    {
        $this->validateOnly('search');
        $this->dispatchLightboxPhotos();
    }

    #[Computed]
    public function gallery(): GalleryModel
    {
        $gallery = GalleryModel::query()
            ->with('client')
            ->whereKey($this->galleryId)
            ->where('is_published', true)
            ->firstOrFail();

        return $gallery;
    }

    #[Computed]
    public function locked(): bool
    {
        return ! app(GalleryAccessService::class)->canViewContent(
            $this->gallery,
            request()->user(),
            request()->session(),
        );
    }

    /** @return Collection<int, GalleryModel> */
    #[Computed]
    public function clientGalleries(): Collection
    {
        $client = $this->gallery->client;

        if ($client === null || $this->locked) {
            return new Collection;
        }

        $access = app(GalleryAccessService::class);
        $user = request()->user();
        $session = request()->session();

        return $client->galleries()
            ->where('is_published', true)
            ->with('client:id,user_id,email')
            ->orderBy('name')
            ->get(['id', 'user_id', 'client_id', 'name', 'slug', 'access_type'])
            ->filter(fn (GalleryModel $gallery): bool => $access->canViewContent($gallery, $user, $session))
            ->values();
    }

    /** @return Collection<int, Photo> */
    #[Computed]
    public function photos(): Collection
    {
        if ($this->locked) {
            return new Collection;
        }

        $query = $this->gallery->photos()->with('tags');

        $this->applySearch($query);

        return $query->get()->each(function (Photo $photo): void {
            $photo->setRelation('gallery', $this->gallery);
            $previewUrl = $photo->previewUrl();
            $photo->setAttribute('preview_url', $previewUrl);
            $photo->setAttribute(
                'thumbnail_url',
                $photo->displayThumbnailUrl(),
            );
            $photo->setAttribute(
                'download_url',
                route('gallery.photo.download', [$this->gallery->slug, $photo->id]),
            );
        });
    }

    #[Computed]
    public function totalPhotoCount(): int
    {
        return $this->locked ? 0 : $this->gallery->photos()->count();
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function lightboxPhotos(): array
    {
        if ($this->locked) {
            return [];
        }

        $slug = $this->gallery->slug;

        return $this->photos
            ->map(function (Photo $photo) use ($slug): array {
                $previewUrl = route('gallery.photo.preview', [$slug, $photo->id]);

                return [
                    'id' => $photo->id,
                    'filename' => $photo->filename,
                    'notes' => $photo->notes,
                    'exif_metadata' => $photo->exif_metadata,
                    'tags' => $photo->tags
                        ->map(fn (PhotoTag $tag): array => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'slug' => $tag->slug,
                        ])
                        ->values()
                        ->all(),
                    'thumbnail' => $photo->displayThumbnailUrl(),
                    'preview_url' => $previewUrl,
                    'download_url' => route('gallery.photo.download', [$slug, $photo->id]),
                ];
            })
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.gallery.viewer');
    }

    private function applySearch(Builder|Relation $query): void
    {
        $search = trim($this->search);

        if ($search === '') {
            return;
        }

        // Escapa curingas do LIKE para que "%" e "_" sejam tratados como texto.
        $term = '%'.addcslashes($search, '\\%_').'%';

        $query->where(function (Builder $photos) use ($term): void {
            $photos
                ->where('filename', 'like', $term)
                ->orWhere('notes', 'like', $term)
                ->orWhereHas('tags', function (Builder $tags) use ($term): void {
                    $tags
                        ->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                });
        });
    }

    private function dispatchLightboxPhotos(): void
    {
        $this->dispatch('gallery-photos-updated', photos: $this->lightboxPhotos);
    }
}
