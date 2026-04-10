const THEME_KEY = 'theme';
const DEFAULT_THEME = 'night';

interface ThemePreset {
    key: string;
    label: string;
    type: 'dark' | 'light';
}

const THEME_PRESETS: ThemePreset[] = [
    { key: 'night', label: 'Dark Blue', type: 'dark' },
    { key: 'dracula', label: 'Dark Purple', type: 'dark' },
    { key: 'forest', label: 'Dark Emerald', type: 'dark' },
    { key: 'winter', label: 'Light Blue', type: 'light' },
    { key: 'lemonade', label: 'Light Warm', type: 'light' },
    { key: 'valentine', label: 'Light Rose', type: 'light' },
];

function getCurrentTheme(): string {
    return localStorage.getItem(THEME_KEY) ?? DEFAULT_THEME;
}

function setTheme(theme: string): void {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem(THEME_KEY, theme);
    updateActiveStates(theme);
}

function updateActiveStates(theme: string): void {
    document.querySelectorAll('[data-theme-swatch]').forEach((el) => {
        const swatchTheme = (el as HTMLElement).dataset.themeSwatch;
        if (swatchTheme === theme) {
            el.classList.add('ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-base-100');
        } else {
            el.classList.remove('ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-base-100');
        }
    });

    const currentLabel = document.getElementById('theme-current-label');
    if (currentLabel) {
        const preset = THEME_PRESETS.find((p) => p.key === theme);
        currentLabel.textContent = preset ? preset.label : theme;
    }
}

// Apply saved theme
setTheme(getCurrentTheme());

// Bind all theme swatch buttons (navbar dropdown + settings page)
document.querySelectorAll('[data-theme-swatch]').forEach((el) => {
    el.addEventListener('click', () => {
        const theme = (el as HTMLElement).dataset.themeSwatch;
        if (theme) {
            setTheme(theme);
            // Close any open DaisyUI dropdown by blurring
            (document.activeElement as HTMLElement)?.blur();
        }
    });
});
