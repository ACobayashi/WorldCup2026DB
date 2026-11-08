document.querySelectorAll('[data-compare-search]').forEach((input) => {
  const label = input.closest('label');
  const select = label?.querySelector('[data-compare-select]');
  const status = label?.querySelector('.wc-search-status');
  if (!select) return;
  const unit = input.dataset.compareUnit || '球员';
  const options = [...select.options].map((option) => ({ value: option.value, text: option.textContent, search: option.dataset.search || option.textContent, selected: option.selected }));
  const render = () => {
    const query = input.value.trim().toLocaleLowerCase('zh-CN');
    const current = select.value;
    const matches = options.filter((option) => !query || option.search.toLocaleLowerCase('zh-CN').includes(query));
    select.replaceChildren(...matches.map((item) => {
      const option = document.createElement('option');
      option.value = item.value; option.textContent = item.text;
      option.dataset.search = item.search;
      option.selected = item.value === current || (!current && item.selected);
      return option;
    }));
    if (status) status.textContent = matches.length ? `找到 ${matches.length} 支${unit}` : `没有匹配${unit}`;
  };
  input.addEventListener('input', render);
});
