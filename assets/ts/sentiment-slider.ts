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
    window.dispatchEvent(
      new CustomEvent("sentiment-changed", { detail: { value } }),
    );
  });
}

function syncSliders(source: HTMLInputElement): void {
  const value = source.value;
  const all = document.querySelectorAll<HTMLInputElement>(
    "#sentiment-slider, .sentiment-slider-mobile",
  );
  for (const slider of all) {
    if (slider !== source) {
      slider.value = value;
    }
  }
}

function attachSlider(slider: HTMLInputElement): void {
  const url = slider.dataset["sentimentUrl"];
  if (!url) return;

  slider.addEventListener("input", () => {
    syncSliders(slider);

    if (debounceTimer !== null) {
      clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
      postSentiment(url, parseInt(slider.value, 10));
    }, DEBOUNCE_MS);
  });
}

function init(): void {
  const desktop = document.getElementById(
    "sentiment-slider",
  ) as HTMLInputElement | null;
  if (desktop) {
    attachSlider(desktop);
  }

  const mobiles = document.querySelectorAll<HTMLInputElement>(
    ".sentiment-slider-mobile",
  );
  for (const mobile of mobiles) {
    attachSlider(mobile);
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
