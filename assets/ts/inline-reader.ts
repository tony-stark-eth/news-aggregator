/**
 * Inline reader UX enhancements:
 * - Smooth scroll-to card on expand (after htmx swap)
 * - Sticky collapse button delegates to the footer toggle
 */

const SCROLL_OFFSET_PX = 16;

/**
 * After htmx swaps content into a .article-content-area,
 * smooth-scroll the parent card to the top of the viewport.
 */
document.addEventListener('htmx:afterSwap', ((event: CustomEvent) => {
    const target = event.detail.target as HTMLElement;
    if (!target.classList.contains('article-content-area')) return;

    // Only scroll when content was loaded (not cleared on collapse)
    if (target.innerHTML.trim().length === 0) return;

    const card = target.closest<HTMLElement>('.card');
    if (!card) return;

    const top = card.getBoundingClientRect().top + window.scrollY - SCROLL_OFFSET_PX;
    window.scrollTo({ top, behavior: 'smooth' });
}) as EventListener);

/**
 * Sticky "Collapse" button inside the expanded content
 * triggers the same toggle as the footer "Read/Collapse" button.
 */
document.addEventListener('click', (event: Event) => {
    const target = event.target as HTMLElement;
    if (!target.classList.contains('inline-reader-collapse')) return;

    const card = target.closest<HTMLElement>('.card');
    if (!card) return;

    const toggle = card.querySelector<HTMLButtonElement>('.article-read-toggle');
    if (toggle) {
        toggle.click();
    }
});
