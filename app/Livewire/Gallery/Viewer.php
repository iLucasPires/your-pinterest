<?php

namespace App\Livewire\Gallery;

use App\Models\Gallery\Gallery as GalleryModel;
use App\Models\Gallery\Photo;
use App\Models\Gallery\PhotoTag;

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

    #[Locked]
    public bool $locked = false;

    #[Url(as: 'q', except: '')]
    #[Validate('string|max:255')]
    public string $search = '';

    #[Url(as: 'tag', except: 0)]
    public int $selectedTagId = 0;

    public function mount(int $galleryId): void
    {
        $this->galleryId = $galleryId;
        $this->locked = $this->gallery->isProtected()
            && ! request()->session()->has("gallery_access_{$galleryId}");

        // Força a resolução da galeria (404 se não publicada).
        $this->gallery;
    }

    public function updatedSearch(): void
    {
        $this->validateOnly('search');
        $this->dispatchLightboxPhotos();
    }

    public function selectTag(int $tagId): void
    {
        abort_if($tagId < 0, 404);
        abort_if($tagId > 0 && ! $this->tags->contains('id', $tagId), 404);

        $this->selectedTagId = $tagId;
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

    /** @return Collection<int, Photo> */
    #[Computed]
    public function photos(): Collection
    {
        if ($this->locked) {
            return new Collection;
        }

        $query = $this->gallery->photos()->with('tags');

        $this->applyTagFilter($query);
        $this->applySearch($query);

        return $query->get()->each(function (Photo $photo): void {
            $photo->setAttribute('preview_url', $photo->previewUrl());
            $photo->setAttribute(
                'download_url',
                route('gallery.photo.download', [$this->gallery->slug, $photo->id]),
            );
        });
    }

    /** @return Collection<int, PhotoTag> */
    #[Computed]
    public function tags(): Collection
    {
        if ($this->locked) {
            return new Collection;
        }

        $inThisGallery = fn (Builder $photos) => $photos->where('photos.gallery_id', $this->galleryId);

        return PhotoTag::query()
            ->whereHas('photos', $inThisGallery)
            ->withCount(['photos as gallery_photo_count' => $inThisGallery])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function totalPhotoCount(): int
    {
        return $this->gallery->photos()->count();
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
                    'tags' => $photo->tags
                        ->map(fn (PhotoTag $tag): array => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'slug' => $tag->slug,
                        ])
                        ->values()
                        ->all(),
                    'thumbnail' => $photo->thumbnail_url ?: $previewUrl,
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

    private function applyTagFilter(Builder|Relation $query): void
    {
        if ($this->selectedTagId <= 0) {
            return;
        }

        $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($this->selectedTagId));
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