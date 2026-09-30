<?php

namespace App\Livewire\Home;

use App\Models\Gallery\Gallery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class GallerySearch extends Component
{
    #[Validate('string|max:255')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->validateOnly('search');
    }

    public function chooseGallery(int $galleryId): RedirectResponse
    {
        return $this->redirectToGallery($galleryId);
    }

    public function accessFirstGallery(): RedirectResponse
    {
        $gallery = $this->galleries->first();

        abort_if($gallery === null, 404);

        return $this->redirectToGallery($gallery->id);
    }

    /** @return Collection<int, Gallery> */
    #[Computed]
    public function galleries(): Collection
    {
        $search = trim($this->search);

        if ($search === '') {
            return new Collection;
        }

        return Gallery::query()
            ->with('client')
            ->where('is_published', true)
            ->publiclyDiscoverable()
            ->where(function ($galleryQuery) use ($search): void {
                $galleryQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search): void {
                        $clientQuery->where('name', 'like', "%{$search}%");
                });
            })
            ->latest('published_at')
            ->limit(8)
            ->get();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.home.gallery-search');
    }

    private function redirectToGallery(int $galleryId): RedirectResponse
    {
        $gallery = Gallery::query()
            ->whereKey($galleryId)
            ->where('is_published', true)
            ->publiclyDiscoverable()
            ->firstOrFail();

        return redirect()->route('gallery.show', $gallery->slug);
    }
}