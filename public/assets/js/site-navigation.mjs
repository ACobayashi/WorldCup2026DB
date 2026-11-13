const header = document.querySelector('[data-session-endpoint]');
if (header) {
  try {
    const response = await fetch(header.dataset.sessionEndpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
    if (response.ok && response.headers.get('content-type')?.includes('application/json')) {
      const result = await response.json();
      if (result.ok && result.data.authenticated) header.querySelector('.site-account').firstChild.textContent = '个人中心 ';
    }
  } catch {
    // Static previews cannot read PHP sessions and keep the login entry.
  }
}
