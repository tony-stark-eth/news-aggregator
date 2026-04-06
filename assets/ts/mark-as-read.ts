const DWELL_TIME_MS = 2000;
const VISIBILITY_THRESHOLD = 0.5;

function markAsRead(articleId: string, card: Element): void {
    void fetch(`/articles/${articleId}/read`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    applyReadStyle(card);
}

function applyReadStyle(card: Element): void {
    card.classList.remove('article-unread');
    card.classList.add('article-read');
}

function isAlreadyRead(card: Element): boolean {
    return card.classList.contains('article-read');
}

// --- Click handler (existing behavior) ---

document.addEventListener('click', (event: Event) => {
    const target = event.target as HTMLElement;
    const link = target.closest<HTMLAnchorElement>('.article-link');
    if (link) {
        const articleId = link.dataset.articleId;
        const card = link.closest<HTMLElement>('[data-article-id]');
        if (articleId && card) {
            markAsRead(articleId, card);
        }
    }
});

// --- Scroll-based auto-mark with dwell timer ---

const dwellTimers = new Map<string, ReturnType<typeof setTimeout>>();

function handleIntersection(entries: IntersectionObserverEntry[]): void {
    for (const entry of entries) {
        const card = entry.target;
        const articleId = card.getAttribute('data-article-id');
        if (!articleId) continue;

        if (entry.isIntersecting) {
            if (isAlreadyRead(card) || dwellTimers.has(articleId)) continue;

            const timer = setTimeout(() => {
                dwellTimers.delete(articleId);
                if (isAlreadyRead(card)) return;

                markAsRead(articleId, card);
                scrollObserver.unobserve(card);
            }, DWELL_TIME_MS);

            dwellTimers.set(articleId, timer);
        } else {
            const existing = dwellTimers.get(articleId);
            if (existing !== undefined) {
                clearTimeout(existing);
                dwellTimers.delete(articleId);
            }
        }
    }
}

const scrollObserver = new IntersectionObserver(handleIntersection, {
    threshold: VISIBILITY_THRESHOLD,
});

function observeArticleCards(): void {
    const cards = document.querySelectorAll<HTMLElement>('[data-article-id]');
    for (const card of cards) {
        if (!isAlreadyRead(card)) {
            scrollObserver.observe(card);
        }
    }
}

// Observe initial cards
observeArticleCards();

// Re-observe after infinite scroll loads new cards
const feed = document.querySelector<HTMLElement>('#article-feed');
if (feed) {
    const mutationObserver = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node instanceof HTMLElement && node.hasAttribute('data-article-id')) {
                    if (!isAlreadyRead(node)) {
                        scrollObserver.observe(node);
                    }
                }
            }
        }
    });

    mutationObserver.observe(feed, { childList: true });
}
