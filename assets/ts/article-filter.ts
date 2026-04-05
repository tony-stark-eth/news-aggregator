const DEBOUNCE_MS = 200;

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function filterArticles(query: string): void {
    const cards = document.querySelectorAll<HTMLElement>('[data-searchable]');
    const normalizedQuery = query.toLowerCase().trim();

    for (const card of cards) {
        const searchable = card.dataset['searchable'] ?? '';

        if (normalizedQuery.length === 0 || searchable.includes(normalizedQuery)) {
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    }
}

const input = document.querySelector<HTMLInputElement>('#article-filter');

if (input) {
    input.addEventListener('input', () => {
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(() => {
            filterArticles(input.value);
        }, DEBOUNCE_MS);
    });
}
