import assert from 'node:assert/strict';
import { readFile, access, readdir } from 'node:fs/promises';
import { createMemberCoursework, signalTiming, personalAssignments } from '../public/assets/js/member-coursework.mjs';

const root = new URL('../', import.meta.url);
assert.equal(Object.keys(personalAssignments).length, 3);
assert.deepEqual(signalTiming(() => 0), { delay: 900, duration: 3200 });
assert.deepEqual(signalTiming(() => 1), { delay: 4100, duration: 7600 });
function mockElement(tag) {
  return { tag, attributes: {}, children: [], setAttribute(key, value) { this.attributes[key] = value; }, append(...items) { this.children.push(...items); }, replaceChildren(...items) { this.children = items; } };
}
const list = mockElement('div');
const panel = { dataset: {}, attributes: {}, querySelector: () => list, querySelectorAll: () => [], setAttribute(key, value) { this.attributes[key] = value; } };
globalThis.window = { matchMedia: () => ({ matches: true, addEventListener() {} }) };
globalThis.document = { hidden: false, createElement: mockElement, addEventListener() {} };
const component = createMemberCoursework({ root: panel, authenticated: false, catalog: { 'lin-ziyuan': { 1: { id: 15, title: '<script>literal title</script>' } } } });
component.setActive(true);
component.selectMember({ id: 'lin-ziyuan', name: '林子媛' });
assert.equal(list.children.length, 3);
assert.equal(list.children[0].href, 'download.php?id=15');
assert.equal(list.children[0].title, '<script>literal title</script>');
assert.match(list.children[0].attributes['aria-label'], /登录后下载/);
assert.equal(list.children[1].href, 'coursework.php#lin-ziyuan');
component.selectMember({ id: 'han-yuchen', name: '韩羽宸' });
assert.equal(list.children.length, 3);
assert.ok(list.children.every((item) => item.href === 'coursework.php#han-yuchen'));
assert.ok(list.children.every((item) => item.attributes['aria-label'].startsWith('韩羽宸')));
delete globalThis.window;
delete globalThis.document;

for (const name of ['public/index.html', 'public/worldcup/index.html', 'public/worldcup/demo.html']) {
  const source = await readFile(new URL(name, root), 'utf8');
  assert.ok(source.includes('site-navigation.css'));
  assert.ok(source.includes('个人介绍') && source.includes('class="site-account"'));
  assert.ok(!source.includes('class="repo-link"'), 'Global navigation must not substitute a repository link for account access');
}
for (const name of ['app/team/views/portal/layout.php', 'app/team/views/guestbook/admin.php', 'app/worldcup/views/layout.php', 'public/index.php']) assert.ok((await readFile(new URL(name, root), 'utf8')).includes('SiteNavigation::html'));
assert.ok(!(await readFile(new URL('public/assets/js/worldcup-experience.mjs', root), 'utf8')).includes('wc-entrance-facts'));
assert.ok(!(await readFile(new URL('app/worldcup/views/layout.php', root), 'utf8')).includes('class="wc-summary"'));
assert.ok(!(await readFile(new URL('public/worldcup/index.html', root), 'utf8')).includes('snapshot-summary'));
assert.ok(!(await readFile(new URL('public/assets/js/member-coursework.mjs', root), 'utf8')).includes('innerHTML'));
for (const slug of ['lin-ziyuan', 'yan-yuanyu', 'ma-yuxiang', 'han-yuchen']) await access(new URL(`data/personal/${slug}/.gitkeep`, root));
await access(new URL('data/team/README.md', root));
await access(new URL('data/install.sql', root));
let phpFiles = 0;
async function auditPhp(directory) {
  for (const item of await readdir(new URL(directory, root), { withFileTypes: true })) {
    const path = `${directory}/${item.name}`;
    if (item.isDirectory()) await auditPhp(path);
    else if (item.name.endsWith('.php')) {
      const source = await readFile(new URL(path, root), 'utf8');
      assert.match(source, /^<\?php\s*(?:\/\/[^\n]*\n\s*)*\/\*\*/, `${path} must have a PHP comment header`);
      phpFiles++;
    }
  }
}
for (const directory of ['app', 'config', 'public', 'scripts']) await auditPhp(directory);
console.log(`Team navigation checks passed: four-column headers, three member assignments, safe text, irregular/reduced-motion signals, removed World Cup counters, submission directories and ${phpFiles} PHP comment headers.`);
