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

        lightboxSource() {
            const photo = this.currentPhoto();

            if (!photo) return '';

            return photo.thumbnail?.includes('googleusercontent.com')
                ? photo.thumbnail.replace(/=s\d+/, '=s1600')
                : photo.preview_url;
        },

        handleImageError(event) {
            const photo = this.currentPhoto();

            if (
                photo &&
                event.target.src !== photo.preview_url
            ) {
                event.target.src = photo.preview_url;
            }
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