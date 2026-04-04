function markAsRead(articleId: string): void {
    void fetch(`/articles/${articleId}/read`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    // Visual feedback: dim the card
    const card = document.querySelector(`[data-article-id="${articleId}"]`);
    if (card) {
        card.classList.add('opacity-60');
    }
}

// Event delegation on article links
document.addEventListener('click', (event: Event) => {
    const target = event.target as HTMLElement;
    const link = target.closest<HTMLAnchorElement>('.article-link');
    if (link) {
        const articleId = link.dataset.articleId;
        if (articleId) {
            markAsRead(articleId);
        }
    }
});
