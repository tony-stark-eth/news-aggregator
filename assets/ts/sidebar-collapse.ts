const STORAGE_KEY = 'sidebar-collapsed';

function isCollapsed(): boolean {
    try {
        return localStorage.getItem(STORAGE_KEY) === 'true';
    } catch {
        return false;
    }
}

function setCollapsed(collapsed: boolean): void {
    try {
        localStorage.setItem(STORAGE_KEY, String(collapsed));
    } catch {
        // localStorage unavailable — silently ignore
    }
}

function applySidebarState(sidebar: HTMLElement, collapsed: boolean): void {
    sidebar.classList.toggle('sidebar-collapsed', collapsed);
    sidebar.classList.toggle('sidebar-expanded', !collapsed);
}

function init(): void {
    const sidebar = document.querySelector<HTMLElement>('[data-sidebar]');
    const toggleBtn = document.querySelector<HTMLElement>('[data-sidebar-toggle]');

    if (!sidebar || !toggleBtn) {
        return;
    }

    // Apply initial state (matches the pre-paint inline script)
    applySidebarState(sidebar, isCollapsed());

    // Enable transitions after initial state is set (prevents flash on load)
    requestAnimationFrame(() => {
        document.documentElement.classList.add('sidebar-ready');
    });

    toggleBtn.addEventListener('click', () => {
        const collapsed = !isCollapsed();
        setCollapsed(collapsed);
        applySidebarState(sidebar, collapsed);
    });
}

init();
