const TOPIC_ARTICLES = '/articles';

interface ArticlePayload {
    type: 'created' | 'enriched';
    articleId: number;
    title: string;
    summary: string | null;
    category: string | null;
    categoryColor: string | null;
    enrichmentMethod: string | null;
    enrichmentStatus: string | null;
    score: number | null;
    keywords: string[] | null;
    translations: Record<string, { title: string; summary: string | null }> | null;
}

let newArticleCount = 0;

function getMercureUrl(): string | null {
    const meta = document.querySelector<HTMLMetaElement>('meta[name="mercure-url"]');
    return meta?.content || null;
}

function subscribeToArticles(hubUrl: string): void {
    const url = new URL(hubUrl);
    url.searchParams.append('topic', TOPIC_ARTICLES);

    // Subscribe to enrichment updates for all visible articles
    const cards = document.querySelectorAll<HTMLElement>('[data-article-id][data-enrichment-status="pending"]');
    for (const card of cards) {
        const articleId = card.dataset.articleId;
        if (articleId) {
            url.searchParams.append('topic', `${TOPIC_ARTICLES}/${articleId}/enriched`);
        }
    }

    const eventSource = new EventSource(url.toString());

    eventSource.onmessage = (event: MessageEvent): void => {
        try {
            const data = JSON.parse(event.data) as ArticlePayload;

            if (data.type === 'created') {
                handleArticleCreated();
            } else if (data.type === 'enriched') {
                handleEnrichmentComplete(data);
            }
        } catch {
            // Silently ignore unparseable messages
        }
    };
}

function handleArticleCreated(): void {
    newArticleCount++;

    const banner = document.getElementById('new-articles-banner');
    const countEl = document.getElementById('new-articles-count');

    if (banner && countEl) {
        const label = newArticleCount === 1 ? 'new article' : 'new articles';
        countEl.textContent = `${newArticleCount} ${label} available. Click to refresh.`;
        banner.classList.remove('hidden');
    }
}

function handleEnrichmentComplete(data: ArticlePayload): void {
    const card = document.querySelector<HTMLElement>(`[data-article-id="${data.articleId}"]`);
    if (!card) {
        return;
    }

    card.dataset.enrichmentStatus = 'complete';

    if (data.title) {
        updateTitle(card, data);
    }

    if (data.summary) {
        updateSummary(card, data.summary);
    }

    if (data.keywords && data.keywords.length > 0) {
        updateKeywords(card, data.keywords);
    }

    if (data.enrichmentMethod) {
        updateEnrichmentBadge(card, data.enrichmentMethod);
    }

    if (data.category && data.categoryColor) {
        updateCategoryBadge(card, data.category, data.categoryColor);
    }

    if (data.score !== null) {
        updateScore(card, data.score);
    }

    if (data.translations) {
        card.dataset.translations = JSON.stringify(data.translations);
    }
}

function updateTitle(card: HTMLElement, data: ArticlePayload): void {
    const titleEl = card.querySelector<HTMLElement>('[data-lang-title]');
    if (titleEl) {
        titleEl.textContent = data.title;
    }

    card.dataset.titleDefault = data.title;
}

function updateSummary(card: HTMLElement, summary: string): void {
    const summaryEl = card.querySelector<HTMLElement>('[data-lang-summary]');
    if (summaryEl) {
        summaryEl.textContent = summary;
    } else {
        // Create summary element if it didn't exist
        const titleContainer = card.querySelector('.flex-1.min-w-0');
        if (titleContainer) {
            const p = document.createElement('p');
            p.className = 'text-sm text-base-content/70 mt-1';
            p.setAttribute('data-lang-summary', '');
            p.textContent = summary;
            const titleH3 = titleContainer.querySelector('h3');
            if (titleH3) {
                titleH3.after(p);
            }
        }
    }

    card.dataset.summaryDefault = summary;
}

function updateKeywords(card: HTMLElement, keywords: string[]): void {
    let container = card.querySelector<HTMLElement>('[data-lang-keywords]');
    if (!container) {
        const titleContainer = card.querySelector('.flex-1.min-w-0');
        if (titleContainer) {
            container = document.createElement('div');
            container.className = 'flex flex-wrap gap-1 mt-1';
            container.setAttribute('data-lang-keywords', '');
            titleContainer.appendChild(container);
        }
    }

    if (container) {
        container.innerHTML = keywords
            .map((kw) => `<span class="badge badge-outline badge-xs">${escapeHtml(kw)}</span>`)
            .join('');
    }
}

function updateEnrichmentBadge(card: HTMLElement, method: string): void {
    const badges = card.querySelectorAll('.badge-ghost.badge-sm');
    for (const badge of badges) {
        if (badge.textContent?.includes('Rule') || badge.textContent?.includes('AI')) {
            badge.textContent = method === 'ai' ? 'AI' : 'Rule';
        }
    }
}

function updateCategoryBadge(card: HTMLElement, name: string, color: string): void {
    const metaRow = card.querySelector('.flex.flex-wrap.items-center');
    if (!metaRow) {
        return;
    }

    // Find existing category badge or create one
    let catBadge = metaRow.querySelector<HTMLElement>('.badge-sm[style*="background-color"]');
    if (!catBadge) {
        catBadge = document.createElement('span');
        catBadge.className = 'badge badge-sm';
        const sourceBadge = metaRow.querySelector('.badge-outline');
        if (sourceBadge) {
            sourceBadge.after(catBadge);
        }
    }

    catBadge.style.backgroundColor = color;
    catBadge.style.color = 'white';
    catBadge.textContent = name;
}

function updateScore(card: HTMLElement, score: number): void {
    const scoreBadge = card.querySelector('.badge-primary, .badge-secondary, .badge-ghost');
    if (scoreBadge && scoreBadge.closest('.flex.items-start')) {
        const pct = Math.round(score * 100);
        scoreBadge.textContent = `${pct}%`;
        scoreBadge.className = `badge badge-sm ${score > 0.7 ? 'badge-primary' : score > 0.4 ? 'badge-secondary' : 'badge-ghost'}`;
    }
}

function escapeHtml(text: string): string {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function initBannerClick(): void {
    const banner = document.getElementById('new-articles-banner');
    if (banner) {
        banner.addEventListener('click', (): void => {
            window.location.reload();
        });
    }
}

function init(): void {
    const hubUrl = getMercureUrl();
    if (!hubUrl) {
        return;
    }

    const articleFeed = document.getElementById('article-feed');
    if (!articleFeed) {
        return;
    }

    subscribeToArticles(hubUrl);
    initBannerClick();
}

document.addEventListener('DOMContentLoaded', init);
