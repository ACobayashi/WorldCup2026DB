const searchDelay = 300;

function filterUrl(form) {
  const url = new URL(form.action, location.href);
  url.search = new URLSearchParams(new FormData(form));
  return url;
}

export function bindFilters(form, apply = (url) => location.assign(url)) {
  let timer;
  let composing = false;
  const search = form.elements.q;
  const update = () => {
    clearTimeout(timer);
    if (!composing && form.checkValidity()) apply(filterUrl(form));
  };
  const schedule = () => {
    clearTimeout(timer);
    timer = setTimeout(update, searchDelay);
  };
  search?.addEventListener('compositionstart', () => {
    composing = true;
    clearTimeout(timer);
  });
  search?.addEventListener('compositionend', () => {
    composing = false;
    schedule();
  });
  search?.addEventListener('input', (event) => {
    if (!event.isComposing && !composing) schedule();
  });
  form.addEventListener('change', (event) => {
    if (!event.defaultPrevented && event.target.matches('select, input[type="date"]')) update();
  });
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    update();
  });
}
