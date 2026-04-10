/**
 * Filterable source dropdown — replaces the native <select> for source filtering.
 *
 * Renders a text input that filters sources by name (case-insensitive substring).
 * Selecting a source navigates to the filtered dashboard URL.
 *
 * Container element: [data-source-filter]
 * Data attributes:
 *   data-sources — JSON array of {id: number|null, name: string, url: string}
 *   data-current-source — current source ID (empty string = all sources)
 */

interface SourceOption {
  id: number | null;
  name: string;
  url: string;
}

function init(): void {
  const container = document.querySelector<HTMLElement>("[data-source-filter]");
  if (!container) return;

  const sourcesRaw = container.dataset["sources"];
  if (!sourcesRaw) return;

  let sources: SourceOption[];
  try {
    sources = JSON.parse(sourcesRaw) as SourceOption[];
  } catch {
    return;
  }

  if (sources.length === 0) return;

  const currentSource = container.dataset["currentSource"] ?? "";
  const allSourcesOption = sources.find((s) => s.id === null);
  const currentOption = currentSource
    ? sources.find((s) => String(s.id) === currentSource)
    : allSourcesOption;

  const input = container.querySelector<HTMLInputElement>(
    "[data-source-filter-input]",
  );
  const dropdown = container.querySelector<HTMLElement>(
    "[data-source-filter-dropdown]",
  );
  const list = container.querySelector<HTMLElement>(
    "[data-source-filter-list]",
  );

  if (!input || !dropdown || !list) return;

  // Pre-fill input with current source name
  if (currentOption) {
    input.value = currentOption.name;
  }

  let activeIndex = -1;
  let visibleItems: HTMLElement[] = [];

  function renderList(filter: string): void {
    if (!list) return;

    const normalized = filter.toLowerCase().trim();
    list.innerHTML = "";
    visibleItems = [];
    activeIndex = -1;

    for (const source of sources) {
      // "All Sources" always visible
      if (
        source.id !== null &&
        normalized.length > 0 &&
        !source.name.toLowerCase().includes(normalized)
      ) {
        continue;
      }

      const item = document.createElement("li");
      const anchor = document.createElement("a");
      anchor.textContent = source.name;
      anchor.href = source.url;
      anchor.classList.add("source-filter-item");

      if (
        (source.id === null && !currentSource) ||
        String(source.id) === currentSource
      ) {
        anchor.classList.add("active");
      }

      anchor.addEventListener("mousedown", (e) => {
        e.preventDefault(); // Prevent input blur before navigation
        window.location.href = source.url;
      });

      item.appendChild(anchor);
      list.appendChild(item);
      visibleItems.push(anchor);
    }

    if (visibleItems.length === 0) {
      const item = document.createElement("li");
      const span = document.createElement("span");
      span.textContent = "No sources found";
      span.classList.add("text-base-content/50", "py-2", "px-4", "text-sm");
      item.appendChild(span);
      list.appendChild(item);
    }
  }

  function openDropdown(): void {
    if (!dropdown) return;
    dropdown.classList.remove("hidden");
    renderList("");
  }

  function closeDropdown(): void {
    if (!dropdown) return;
    dropdown.classList.add("hidden");
    activeIndex = -1;
    clearHighlight();
  }

  function clearHighlight(): void {
    for (const item of visibleItems) {
      item.classList.remove("focus");
    }
  }

  function setHighlight(index: number): void {
    clearHighlight();
    const target = visibleItems[index];
    if (target) {
      target.classList.add("focus");
      target.scrollIntoView({ block: "nearest" });
    }
  }

  // Input events
  input.addEventListener("focus", () => {
    input.select();
    openDropdown();
  });

  input.addEventListener("input", () => {
    renderList(input.value);
    if (!dropdown?.classList.contains("hidden") && visibleItems.length > 0) {
      activeIndex = 0;
      setHighlight(0);
    }
  });

  input.addEventListener("blur", () => {
    // Delay close to allow mousedown on items
    setTimeout(() => {
      closeDropdown();
      // Restore current value if user didn't select
      if (currentOption) {
        input.value = currentOption.name;
      }
    }, 150);
  });

  input.addEventListener("keydown", (e: KeyboardEvent) => {
    if (dropdown?.classList.contains("hidden")) {
      if (e.key === "ArrowDown" || e.key === "Enter") {
        openDropdown();
        e.preventDefault();
        return;
      }
      return;
    }

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        if (visibleItems.length > 0) {
          activeIndex = (activeIndex + 1) % visibleItems.length;
          setHighlight(activeIndex);
        }
        break;

      case "ArrowUp":
        e.preventDefault();
        if (visibleItems.length > 0) {
          activeIndex =
            activeIndex <= 0 ? visibleItems.length - 1 : activeIndex - 1;
          setHighlight(activeIndex);
        }
        break;

      case "Enter":
        e.preventDefault();
        if (activeIndex >= 0 && activeIndex < visibleItems.length) {
          const selected = visibleItems[activeIndex];
          if (selected?.href) {
            window.location.href = selected.href;
          }
        } else if (visibleItems.length > 0 && visibleItems[0]?.href) {
          // Select first visible option
          window.location.href = visibleItems[0].href;
        }
        break;

      case "Escape":
        e.preventDefault();
        closeDropdown();
        input.blur();
        break;
    }
  });

  // Initial render
  renderList("");
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}
