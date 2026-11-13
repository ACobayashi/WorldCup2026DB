(() => {
  let theme = 'light';
  try {
    if (localStorage.getItem('fourward.worldcup.theme') === 'dark') theme = 'dark';
  } catch {
    // The default remains usable when browser storage is unavailable.
  }
  document.documentElement.dataset.wcTheme = theme;
})();
