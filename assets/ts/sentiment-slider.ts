/**
 * Sentiment slider — persists filter preference via POST to server.
 *
 * Debounces 300ms before sending. After POST, triggers htmx to reload
 * the article feed. Double-click/tap resets to 0.
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
    // Trigger htmx to reload the article feed + notice
    const feed = document.getElementById("article-feed");
    if (feed) {
      // Use htmx ajax to reload the current dashboard page into the body
      const htmx = (window as unknown as Record<string, unknown>)["htmx"] as
        | { ajax: (method: string, url: string, target: string) => void }
        | undefined;
      if (htmx) {
        htmx.ajax("GET", window.location.href, "body");
        return;
      }
    }
    // Fallback if htmx not available or no feed element
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

  // Double-click/tap resets to 0
  slider.addEventListener("dblclick", () => {
    slider.value = "0";

    if (debounceTimer !== null) {
      clearTimeout(debounceTimer);
    }

    postSentiment(url, 0);
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
