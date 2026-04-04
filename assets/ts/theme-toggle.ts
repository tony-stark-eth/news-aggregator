const THEME_KEY = 'theme';
const DARK_THEME = 'night';
const LIGHT_THEME = 'winter';

function getCurrentTheme(): string {
    return localStorage.getItem(THEME_KEY) ?? DARK_THEME;
}

function setTheme(theme: string): void {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem(THEME_KEY, theme);
}

function toggleTheme(): void {
    const current = getCurrentTheme();
    setTheme(current === DARK_THEME ? LIGHT_THEME : DARK_THEME);
}

// Apply saved theme
setTheme(getCurrentTheme());

// Bind toggle button
const toggleBtn = document.getElementById('theme-toggle');
if (toggleBtn) {
    toggleBtn.addEventListener('click', toggleTheme);
}
