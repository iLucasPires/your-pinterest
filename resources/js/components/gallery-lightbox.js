// resources/js/components/gallery-lightbox.js

export default function galleryLightbox(initialPhotos = []) {
    return {
        allPhotos: initialPhotos,
        current: 0,
        lightboxOpen: false,
        lastFocusedElement: null,
        touchStartX: 0,

        setPhotos(photos) {
            const currentPhotoId = this.currentPhoto()?.id;

            this.allPhotos = photos;

            const currentIndex = this.allPhotos.findIndex(
                photo => photo.id === currentPhotoId
            );

            this.current = currentIndex >= 0
                ? currentIndex
                : Math.min(
                    this.current,
                    Math.max(this.allPhotos.length - 1, 0)
                );

            if (!this.allPhotos.length && this.lightboxOpen) {
                this.closeLightbox();
            }
        },

        openLightbox(index) {
            if (!this.allPhotos.length) return;

            this.current = index;
            this.lastFocusedElement = document.activeElement;
            this.lightboxOpen = true;

            document.body.style.overflow = 'hidden';

            this.$nextTick(() => {
                this.$refs.lightbox?.focus();
            });
        },

        closeLightbox() {
            this.lightboxOpen = false;
            document.body.style.overflow = '';

            this.lastFocusedElement?.focus?.();
        },

        navigateLightbox(direction) {
            if (!this.allPhotos.length) return;

            this.current = (
                this.current +
                direction +
                this.allPhotos.length
            ) % this.allPhotos.length;
        },

        currentPhoto() {
            return this.allPhotos[this.current] ?? null;
        },

        currentExifEntries() {
            const metadata = this.currentPhoto()?.exif_metadata;

            if (!metadata) return [];

            const entries = [];
            const camera = [metadata.camera_make, metadata.camera_model]
                .filter(Boolean)
                .join(' ');

            if (camera) {
                entries.push({ label: 'Câmera', value: camera });
            }
            if (metadata.lens) {
                entries.push({ label: 'Lente', value: metadata.lens });
            }

            if (metadata.iso) {
                entries.push({ label: 'ISO', value: metadata.iso });
            }

            if (metadata.aperture) {
                entries.push({ label: 'Abertura', value: `f/${metadata.aperture}` });
            }

            if (metadata.shutter_speed) {
                entries.push({ label: 'Exposição', value: `${metadata.shutter_speed} s` });
            }

            if (metadata.focal_length_mm) {
                entries.push({ label: 'Distância focal', value: `${metadata.focal_length_mm} mm` });
            }

            if (metadata.captured_at) {
                entries.push({ label: 'Capturada em', value: metadata.captured_at });
            }

            return entries;
        },

        lightboxSource() {
            if (!this.lightboxOpen) return null;

            const photo = this.currentPhoto();

            return photo?.preview_url || photo?.thumbnail || '';
        },

        handleKeydown(event) {
            if (!this.lightboxOpen) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                this.closeLightbox();
            }

            if (event.key === 'ArrowRight') {
                event.preventDefault();
                this.navigateLightbox(1);
            }

            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                this.navigateLightbox(-1);
            }
        },

        handleTouchStart(event) {
            this.touchStartX = event.changedTouches[0].clientX;
        },

        handleTouchEnd(event) {
            const distance =
                event.changedTouches[0].clientX - this.touchStartX;

            if (Math.abs(distance) > 50) {
                this.navigateLightbox(
                    distance < 0 ? 1 : -1
                );
            }
        },
    };
}
