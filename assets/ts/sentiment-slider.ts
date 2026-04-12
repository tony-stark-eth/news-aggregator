/**
 * Sentiment slider — persists filter preference via POST to server.
 *
 * Debounces 300ms before sending. On change, dispatches a custom
 * "sentiment-changed" event so the dashboard can refresh.
 */

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
const DEBOUNCE_MS = 300;

function postSentiment(url: string, value: number): void {
  const body = new URLSearchParams();
  body.set("value", String(value));

  fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body,
  }).then(() => {
    // Reload the page to reflect new sentiment ranking
    window.location.reload();
  });
}

function init(): void {
  const slider = document.getElementById(
    "sentiment-slider",
  ) as HTMLInputElement | null;
  if (!slider) return;

  const url = slider.dataset["sentimentUrl"];
  if (!url) return;

  slider.addEventListener("input", () => {
    if (debounceTimer !== null) {
      clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
      postSentiment(url, parseInt(slider.value, 10));
    }, DEBOUNCE_MS);
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
