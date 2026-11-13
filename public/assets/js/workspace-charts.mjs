const source = document.getElementById('workspace-chart-data');
const feedback = document.getElementById('chart-feedback');
const payload = JSON.parse(source.textContent);
const selector = document.getElementById('chart-status');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
let library;
try {
  await import('../vendor/echarts/echarts.min.js');
  library = window.echarts;
} catch {
  // The table remains available if the bundled chart library cannot load.
}
const filesChart = library?.init(document.getElementById('coursework-chart'), null, { renderer: 'svg' });
const accountElement = document.getElementById('account-chart');
const accountsChart = accountElement && library?.init(accountElement, null, { renderer: 'svg' });

function render() {
  const owners = Object.entries(payload.owners);
  const counts = Object.fromEntries(owners.map(([slug]) => [slug, { draft: 0, published: 0 }]));
  for (const row of payload.stats.files) if (counts[row.owner_slug]) counts[row.owner_slug][row.status] = Number(row.total);
  const values = owners.map(([slug]) => selector.value === 'all' ? counts[slug].draft + counts[slug].published : counts[slug][selector.value]);
  document.getElementById('chart-empty').hidden = values.some((value) => value > 0);
  document.getElementById('chart-table').replaceChildren(...owners.map(([slug, label]) => {
    const tr = document.createElement('tr');
    for (const value of [label, counts[slug].draft, counts[slug].published, counts[slug].draft + counts[slug].published]) {
      const td = document.createElement('td');
      td.textContent = String(value);
      tr.append(td);
    }
    return tr;
  }));
  for (const item of document.querySelectorAll('[data-role-count]')) item.textContent = String(payload.stats.roles?.[item.dataset.roleCount] || 0);
  if (!library) {
    feedback.textContent = '图表组件未能加载，仍可通过下方原始数据表查看统计。';
    return;
  }
  const common = { animation: !reducedMotion.matches, textStyle: { color: '#c7d0e4', fontFamily: 'sans-serif' }, aria: { enabled: true }, tooltip: { trigger: 'axis', renderMode: 'richText' } };
  filesChart.setOption({ ...common, grid: { left: 84, right: 20, top: 20, bottom: 36 }, xAxis: { type: 'value', minInterval: 1, axisLabel: { color: '#c7d0e4' }, splitLine: { lineStyle: { color: '#ffffff16' } } }, yAxis: { type: 'category', inverse: true, data: owners.map(([, label]) => label), axisLabel: { color: '#c7d0e4', interval: 0, width: 72, overflow: 'truncate' }, axisLine: { show: false }, axisTick: { show: false } }, series: [{ name: selector.selectedOptions[0].textContent, type: 'bar', data: values, itemStyle: { color: '#8b9cff', borderRadius: [0, 5, 5, 0] }, barMaxWidth: 40 }] });
  if (accountsChart && payload.stats.roles) accountsChart.setOption({ ...common, tooltip: { trigger: 'item', renderMode: 'richText' }, color: ['#8b9cff', '#a8c6f1', '#b7a2e8'], legend: { bottom: 0, textStyle: { color: '#c7d0e4' } }, series: [{ name: '账号身份', type: 'pie', radius: ['35%', '65%'], label: { show: false }, data: [['user', '普通用户'], ['member', '团队成员'], ['admin', '管理员']].map(([key, name]) => ({ name, value: Number(payload.stats.roles[key] || 0) })) }] });
}

selector.addEventListener('change', render);
document.getElementById('refresh-charts').addEventListener('click', async (event) => {
  event.currentTarget.disabled = true;
  try {
    const response = await fetch('workspace.php?section=stats&format=json', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error('无法刷新，请确认仍处于登录状态。');
    payload.stats = await response.json();
    render();
    if (library) feedback.textContent = '已刷新当前数据库统计。';
  } catch (error) { feedback.textContent = error.message; }
  finally { document.getElementById('refresh-charts').disabled = false; }
});
if (filesChart) new ResizeObserver(() => { filesChart.resize(); accountsChart?.resize(); }).observe(document.getElementById('coursework-chart'));
reducedMotion.addEventListener('change', render);
render();
