import { bindFilters } from './worldcup-filters.mjs';

for (const form of document.querySelectorAll('[data-wc-auto-filter]')) {
  const results = document.querySelector('[data-wc-filter-results]');
  if (!results) {
    bindFilters(form);
    continue;
  }

  let pending;
  bindFilters(form, async (url) => {
    pending?.abort();
    const request = new AbortController();
    pending = request;
    const search = form.elements.q;
    const query = search?.value;
    results.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url, { signal: request.signal });
      if (!response.ok) throw new Error('Could not load filtered results');
      const html = await response.text();
      if (request.signal.aborted || query !== search?.value) return;
      const page = new DOMParser().parseFromString(html, 'text/html');
      const next = page.querySelector('[data-wc-filter-results]');
      if (!next) throw new Error('Filtered results are missing');
      results.replaceChildren(...next.childNodes);
      for (const selector of ['[data-wc-filter-pagination]', '[data-wc-filter-nav]']) {
        const region = document.querySelector(selector);
        const updated = page.querySelector(selector);
        if (region && updated) region.replaceChildren(...updated.childNodes);
      }
      history.replaceState(history.state, '', url);
    } catch {
      if (!request.signal.aborted && query === search?.value) location.assign(url);
    } finally {
      if (pending === request) results.setAttribute('aria-busy', 'false');
    }
  });
}
