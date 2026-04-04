interface ScrollConfig {
    sentinel: string;
    container: string;
    spinner: string;
    endMessage: string;
}

const config: ScrollConfig = {
    sentinel: '#scroll-sentinel',
    container: '#article-feed',
    spinner: '#scroll-spinner',
    endMessage: '#no-more-articles',
};

let currentPage = 1;
let loading = false;
let exhausted = false;

function getNextPageUrl(): string | null {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(currentPage + 1));
    url.searchParams.set('_fragment', '1');
    return url.toString();
}

async function loadNextPage(): Promise<void> {
    if (loading || exhausted) return;

    const container = document.querySelector(config.container);
    const spinner = document.querySelector(config.spinner);
    const endMessage = document.querySelector(config.endMessage);
    if (!container) return;

    loading = true;

    try {
        const url = getNextPageUrl();
        if (!url) return;

        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            exhausted = true;
            return;
        }

        const html = await response.text();
        if (html.trim() === '') {
            exhausted = true;
            spinner?.classList.add('hidden');
            endMessage?.classList.remove('hidden');
            return;
        }

        container.insertAdjacentHTML('beforeend', html);
        currentPage++;

        // Re-run timeago on new elements
        if (typeof window.updateTimeago === 'function') {
            window.updateTimeago();
        }
    } catch {
        exhausted = true;
    } finally {
        loading = false;
    }
}

// Set up IntersectionObserver
const sentinel = document.querySelector(config.sentinel);
if (sentinel) {
    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    void loadNextPage();
                }
            }
        },
        { rootMargin: '200px' },
    );
    observer.observe(sentinel);
}

// Declare global for timeago integration
declare global {
    interface Window {
        updateTimeago?: () => void;
    }
}
