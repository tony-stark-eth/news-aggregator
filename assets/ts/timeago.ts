const MINUTE = 60;
const HOUR = 3600;
const DAY = 86400;

function formatRelativeTime(date: Date): string {
    const now = new Date();
    const diffSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffSeconds < MINUTE) {
        return 'just now';
    }
    if (diffSeconds < HOUR) {
        const minutes = Math.floor(diffSeconds / MINUTE);
        return `${minutes}m ago`;
    }
    if (diffSeconds < DAY) {
        const hours = Math.floor(diffSeconds / HOUR);
        return `${hours}h ago`;
    }
    if (diffSeconds < DAY * 7) {
        const days = Math.floor(diffSeconds / DAY);
        return `${days}d ago`;
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function updateTimeago(): void {
    const elements = document.querySelectorAll<HTMLTimeElement>('time.timeago');
    for (const el of elements) {
        const datetime = el.getAttribute('datetime');
        if (datetime) {
            const date = new Date(datetime);
            if (!isNaN(date.getTime())) {
                el.textContent = formatRelativeTime(date);
                el.title = new Intl.DateTimeFormat(undefined, {
                    dateStyle: 'full',
                    timeStyle: 'short',
                }).format(date);
            }
        }
    }
}

// Initial render + interval
updateTimeago();
setInterval(updateTimeago, 60_000);

// Re-run after htmx swaps new content into the DOM
document.addEventListener('htmx:afterSwap', () => {
    updateTimeago();
});
