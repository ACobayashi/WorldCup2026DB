export function bindComparisonSearch(form) {
  const pickers = [...form.querySelectorAll('[data-compare-select]')];
  for (const [index, select] of pickers.entries()) {
    const label = select.closest('label');
    const input = label.querySelector('[data-compare-search]');
    const status = label.querySelector('.wc-search-status');
    const unit = input.dataset.compareUnit === '球队' ? '支球队' : '名球员';
    const other = pickers[1 - index].value;
    const current = select.value;
    const options = [...select.options].filter((option) => option.value !== other || option.value === current);
    const selected = options.find((option) => option.value === current);

    const render = () => {
      const query = input.value.trim().toLocaleLowerCase('zh-CN');
      const matches = options.filter((option) => (option.dataset.search || option.textContent).toLocaleLowerCase('zh-CN').includes(query));
      select.replaceChildren(...(matches.includes(selected) ? matches : [selected, ...matches]));
      select.value = current;
      status.textContent = query ? (matches.length ? `找到 ${matches.length} ${unit}` : '没有匹配的结果') : '';
    };
    input.addEventListener('input', render);
    render();
  }
}

for (const form of document.querySelectorAll('.wc-compare-form')) bindComparisonSearch(form);
