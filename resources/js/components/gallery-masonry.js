export default function galleryMasonry() {
    return {
        resizeObserver: null,
        mutationObserver: null,
        observedCards: new WeakSet(),

        init() {
            const grid = this.$el;

            this.resizeObserver = new ResizeObserver(entries => {
                const { gridAutoRows, rowGap } = getComputedStyle(grid);
                const rowHeight = parseFloat(gridAutoRows);
                const gapHeight = parseFloat(rowGap);

                for (const { target: card } of entries) {
                    const rowSpan = Math.ceil(
                        (card.getBoundingClientRect().height + gapHeight) /
                        (rowHeight + gapHeight)
                    );

                    card.style.gridRowEnd = `span ${rowSpan}`;
                }
            });

            const observeCards = () => {
                for (const card of grid.children) {
                    if (this.observedCards.has(card)) continue;

                    this.observedCards.add(card);
                    this.resizeObserver.observe(card);
                }
            };

            observeCards();

            this.mutationObserver = new MutationObserver(observeCards);
            this.mutationObserver.observe(grid, { childList: true });
        },

        destroy() {
            this.resizeObserver?.disconnect();
            this.mutationObserver?.disconnect();
        },
    };
}
