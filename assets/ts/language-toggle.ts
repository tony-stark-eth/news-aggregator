/**
 * Language toggle — switches article cards between translated and original text.
 *
 * Articles from non-English sources have data-title-translated/data-title-original
 * and data-summary-translated/data-summary-original attributes. This toggle swaps
 * the displayed text between the two versions.
 *
 * Persists preference in localStorage.
 */
const STORAGE_KEY = "display-language";

type Lang = "translated" | "original";

function getPreference(): Lang {
  return (localStorage.getItem(STORAGE_KEY) as Lang) || "translated";
}

function applyLanguage(lang: Lang): void {
  const cards = document.querySelectorAll<HTMLElement>("[data-article-id]");

  for (const card of cards) {
    const titleEl = card.querySelector<HTMLElement>("[data-lang-title]");
    const summaryEl = card.querySelector<HTMLElement>("[data-lang-summary]");

    if (titleEl) {
      const translated = card.dataset.titleTranslated;
      const original = card.dataset.titleOriginal;
      if (translated && original) {
        titleEl.textContent = lang === "original" ? original : translated;
      }
    }

    if (summaryEl) {
      const translated = card.dataset.summaryTranslated;
      const original = card.dataset.summaryOriginal;
      if (translated && original) {
        summaryEl.textContent = lang === "original" ? original : translated;
      }
    }
  }

  const btn = document.getElementById("lang-toggle");
  const label = document.getElementById("lang-toggle-label");
  if (label && btn) {
    if (lang === "original") {
      label.textContent = "Original";
      btn.classList.add("btn-active");
    } else {
      label.textContent = "Translated";
      btn.classList.remove("btn-active");
    }
  }
}

function init(): void {
  const btn = document.getElementById("lang-toggle");
  if (!btn) return;

  const current = getPreference();
  applyLanguage(current);

  btn.addEventListener("click", () => {
    const next: Lang = getPreference() === "translated" ? "original" : "translated";
    localStorage.setItem(STORAGE_KEY, next);
    applyLanguage(next);
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
