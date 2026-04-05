/**
 * Language selector — switches article cards between available translation languages.
 *
 * Articles with translations have a `data-translations` JSON attribute containing
 * the translations map: {"en": {"title": "...", "summary": "..."}, "de": {...}, ...}
 *
 * The selector reads the user's preferred language from localStorage and applies
 * the matching translation to each article card. Falls back to the server-rendered
 * default text when a language is not available for a given article.
 *
 * Persists preference in localStorage.
 */
const STORAGE_KEY = "display-language";
const DEFAULT_LANG = "en";

interface Translation {
  title: string;
  summary: string | null;
}

type TranslationsMap = Record<string, Translation>;

function getPreference(): string {
  return localStorage.getItem(STORAGE_KEY) || DEFAULT_LANG;
}

function applyLanguage(lang: string): void {
  const cards = document.querySelectorAll<HTMLElement>("[data-article-id]");

  for (const card of cards) {
    const translationsRaw = card.dataset["translations"];
    const titleEl = card.querySelector<HTMLElement>("[data-lang-title]");
    const summaryEl = card.querySelector<HTMLElement>("[data-lang-summary]");

    if (!translationsRaw || translationsRaw === "null") {
      continue;
    }

    let translations: TranslationsMap;
    try {
      translations = JSON.parse(translationsRaw) as TranslationsMap;
    } catch {
      continue;
    }

    const translation = translations[lang];

    if (titleEl) {
      if (translation) {
        titleEl.textContent = translation.title;
      } else {
        // Fall back to server-rendered default
        const defaultTitle = card.dataset["titleDefault"];
        if (defaultTitle) {
          titleEl.textContent = defaultTitle;
        }
      }
    }

    if (summaryEl) {
      if (translation?.summary) {
        summaryEl.textContent = translation.summary;
      } else {
        const defaultSummary = card.dataset["summaryDefault"];
        if (defaultSummary) {
          summaryEl.textContent = defaultSummary;
        }
      }
    }
  }

  // Update button label
  const label = document.getElementById("lang-selector-label");
  if (label) {
    label.textContent = lang.toUpperCase();
  }

  // Update active state in dropdown
  const options = document.querySelectorAll<HTMLElement>(".lang-option");
  for (const opt of options) {
    if (opt.dataset["lang"] === lang) {
      opt.classList.add("active");
    } else {
      opt.classList.remove("active");
    }
  }
}

function init(): void {
  const btn = document.getElementById("lang-selector-btn");
  if (!btn) return;

  const current = getPreference();
  applyLanguage(current);

  // Handle language option clicks
  const options = document.querySelectorAll<HTMLElement>(".lang-option");
  for (const opt of options) {
    opt.addEventListener("click", (e) => {
      e.preventDefault();
      const lang = opt.dataset["lang"];
      if (!lang) return;

      localStorage.setItem(STORAGE_KEY, lang);
      applyLanguage(lang);

      // Close the dropdown by blurring
      const activeEl = document.activeElement;
      if (activeEl instanceof HTMLElement) {
        activeEl.blur();
      }
    });
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
