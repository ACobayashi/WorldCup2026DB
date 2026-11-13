import assert from 'node:assert/strict';
import { readFile, access } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { inflateSync } from 'node:zlib';
import { runInNewContext } from 'node:vm';
import { members, safeGithubUrl } from '../public/assets/js/members.mjs';
import { normalizeMessages, createMessage } from '../public/assets/js/messages.mjs';
import {
  demoMatches, DEMO_SNAPSHOT_AT, beijingDate, selectFeaturedMatch,
  filterMatches, normalizeSupportChoices, supportStats, scoreLabel, loadMatchFeed,
} from '../public/assets/js/worldcup-data.mjs';
import {
  MOTION_TIMINGS,
  animateMemberEntrance,
  memberEntranceKeyframes,
  sceneTransitionKeyframes,
  transitionScenes,
} from '../public/assets/js/motion.mjs';
import { normalizeWorldcupTheme, shouldShowWorldcupEntrance, worldcupEntranceKeyframes, worldcupEntranceStrips, WORLDCUP_THEME_KEY, WORLDCUP_REVEAL_TIMING } from '../public/assets/js/worldcup-experience.mjs';

const root = new URL('../', import.meta.url);
const ids = members.map((member) => member.id);
assert.equal(members.length, 4);
assert.equal(new Set(ids).size, 4);
assert.deepEqual(members.map((member) => member.github), [
  'https://github.com/ACobayashi',
  'https://github.com/yanyuanyu',
  'https://github.com/s1nk141',
  'https://github.com/Smile-hyc',
]);
assert.deepEqual(members.map((member) => member.name), ['林子媛', '严苑毓', '马禹翔', '韩羽宸']);
assert.equal(members.filter((member) => member.gender === 'female').length, 2);
assert.equal(members.filter((member) => member.gender === 'male').length, 2);
assert.deepEqual(members.map((member) => member.school), ['密码与网络空间安全学院', '密码与网络空间安全学院', '计算机学院', '计算机学院']);
assert.equal(new Set(members.map((member) => member.portrait)).size, 4, 'Members must have independent portrait files');
members.forEach((member) => {
  assert.ok(member.role && member.task && member.quote);
  assert.equal(member.portrait, `assets/images/members/${member.id}.png`);
  assert.ok(member.portraitFocus > 0 && member.portraitFocus < 1);
  assert.ok(member.github === null || safeGithubUrl(member.github), 'GitHub links must be unset or valid HTTPS GitHub URLs');
});

assert.equal(safeGithubUrl('https://github.com/example'), 'https://github.com/example');
assert.equal(safeGithubUrl('javascript:alert(1)'), null);
assert.equal(safeGithubUrl('https://github.com.example.com/user'), null);
assert.equal(safeGithubUrl('https://name:secret@github.com/user'), null);
assert.equal(safeGithubUrl('http://github.com/user'), null);
assert.equal(safeGithubUrl(null), null);

const message = createMessage(ids[0], '', '  你好！  ', '2026-10-12T10:00:00.000Z');
assert.equal(message.author, '一位访客');
assert.equal(message.message, '你好！');
assert.throws(() => createMessage(ids[0], '访客', '   '));
assert.throws(() => createMessage(ids[0], '访客', 'a'.repeat(281)));
assert.equal(Array.from(createMessage('team', '😀'.repeat(24), '😀'.repeat(280)).message).length, 280);
assert.throws(() => createMessage('team', '名'.repeat(25), '你好'));
assert.equal(normalizeMessages([{ ...message, memberId: 'team' }], ['team', ...ids]).length, 1);
assert.deepEqual(normalizeMessages(null, ids), []);
assert.equal(normalizeMessages([message, { ...message, memberId: 'unknown' }, { ...message, createdAt: 'invalid' }, { ...message, message: '' }], ids).length, 1);
assert.equal(normalizeMessages(Array.from({ length: 90 }, () => message), ids).length, 80);
assert.equal(normalizeMessages([{ ...message, message: '<img src=x onerror=alert(1)>' }], ids)[0].message, '<img src=x onerror=alert(1)>');

function translation(frame) {
  const values = frame.transform.match(/translate3d\(([^)]+)\)/);
  assert.ok(values, 'Motion keyframes must provide a translate3d transform');
  return values[1].split(',').map((value) => Number.parseFloat(value));
}

const entranceFrames = memberEntranceKeyframes();
assert.ok(translation(entranceFrames.portrait[0])[0] < 0, 'Portraits must enter from the left');
assert.equal(translation(entranceFrames.portrait.at(-1))[0], 0);
assert.ok(translation(entranceFrames.backdrop[0])[0] > 0, 'Face backdrops must enter from the right');
assert.equal(translation(entranceFrames.backdrop.at(-1))[0], 0);
assert.ok(MOTION_TIMINGS.portrait > 1000, 'Portrait motion must include a long braking finish');
assert.ok(MOTION_TIMINGS.backdrop > MOTION_TIMINGS.portrait, 'Backdrops must drift more slowly than portraits');
const forwardFrames = sceneTransitionKeyframes(1);
const reverseFrames = sceneTransitionKeyframes(-1);
for (const [scene, index] of [['outgoing', 1], ['incoming', 0]]) {
  const forwardY = translation(forwardFrames[scene][index])[1];
  const reverseY = translation(reverseFrames[scene][index])[1];
  assert.notEqual(forwardY, 0);
  assert.equal(forwardY, -reverseY, 'Reversing scenes must reverse their travel direction');
}

function mockMotionElement(pending = false) {
  const calls = [];
  return {
    calls,
    animate(keyframes, options) {
      let rejectFinished;
      const animation = {
        finished: pending
          ? new Promise((resolve, reject) => { rejectFinished = reject; })
          : Promise.resolve(),
        cancelCount: 0,
        cancel() {
          animation.cancelCount += 1;
          rejectFinished?.(new Error('Animation cancelled'));
        },
      };
      calls.push({ keyframes, options, animation });
      return animation;
    },
  };
}

const reducedTarget = mockMotionElement();
const reducedIncoming = mockMotionElement();
assert.deepEqual(animateMemberEntrance({
  portrait: reducedTarget,
  backdrop: reducedTarget,
  copy: reducedTarget,
  reducedMotion: true,
}), []);
assert.deepEqual(await transitionScenes({
  outgoing: reducedTarget,
  incoming: reducedIncoming,
  reducedMotion: true,
}), { animated: false, cancelled: false });
assert.equal(reducedTarget.calls.length, 0, 'Reduced-motion settings must skip animation entirely');
assert.equal(reducedIncoming.calls.length, 0, 'Reduced-motion settings must also skip incoming scene animation');
assert.deepEqual(animateMemberEntrance({}), []);
assert.deepEqual(await transitionScenes({ outgoing: {}, incoming: {} }), { animated: false, cancelled: false });

const portraitTarget = mockMotionElement();
const backdropTarget = mockMotionElement();
const copyTarget = mockMotionElement();
assert.equal(animateMemberEntrance({ portrait: portraitTarget, backdrop: backdropTarget, copy: copyTarget }).length, 3);
assert.equal(portraitTarget.calls[0].options.duration, MOTION_TIMINGS.portrait);
assert.equal(backdropTarget.calls[0].options.duration, MOTION_TIMINGS.backdrop);

const outgoingTarget = mockMotionElement();
const incomingTarget = mockMotionElement();
assert.deepEqual(await transitionScenes({ outgoing: outgoingTarget, incoming: incomingTarget }), { animated: true, cancelled: false });
for (const target of [outgoingTarget, incomingTarget]) {
  assert.equal(target.calls[0].animation.cancelCount, 1, 'Completed scene motion must release its fill styles');
}
const interruptedTarget = mockMotionElement(true);
const finishingTarget = mockMotionElement();
const interruptedTransition = transitionScenes({ outgoing: interruptedTarget, incoming: finishingTarget });
interruptedTarget.calls[0].animation.cancel();
assert.deepEqual(await interruptedTransition, { animated: true, cancelled: true });
assert.equal(interruptedTarget.calls[0].animation.cancelCount, 2, 'Cancelled motion must still run scene cleanup');
assert.equal(finishingTarget.calls[0].animation.cancelCount, 1, 'Cancellation must also release the other scene');

const html = await readFile(new URL('public/index.html', root), 'utf8');
const downloads = await readFile(new URL('public/downloads.html', root), 'utf8');
assert.ok(html.includes('href="downloads.html"'), 'Pages homepage must link to the static download page');
assert.ok(downloads.includes('https://github.com/ACobayashi/WorldCup2026DB/archive/refs/heads/main.zip'), 'Pages must link to the team source ZIP');
assert.ok(!/href="[^"]*(?:\.php|storage\/course-downloads|assignment-[123]\.zip)/.test(downloads), 'Static download page must not link to PHP or private archives');
await access(new URL('public/assets/css/course-materials.css', root));
const htmlIds = [...html.matchAll(/\bid="([^"]+)"/g)].map((match) => match[1]);
assert.equal(htmlIds.length, new Set(htmlIds).size, 'HTML IDs must be unique');
for (const id of ['member-selector', 'member-portrait-image', 'member-face-image', 'message-form', 'message-dialog', 'member-tags', 'team-motto', 'team-stands-list', 'open-team-message', 'team-workspace', 'message-pagination', 'message-submit']) assert.ok(htmlIds.includes(id));
assert.ok(html.includes('name="guestbook-mode" content="demo"'));
const phpEntry = await readFile(new URL('public/index.php', root), 'utf8');
assert.ok(phpEntry.includes('name="guestbook-mode" content="server"'), 'The PHP entry must retain server-mode guestbook integration');
assert.ok(html.includes('href="worldcup/"'), 'The homepage must link to the actual match center');
assert.ok(!html.includes('所属学院'), 'The homepage must not display the removed college-count statistic');
assert.ok(!html.includes('四个名字，一次共同创作'), 'The removed team introduction tagline must not reappear');
const app = await readFile(new URL('public/assets/js/app.mjs', root), 'utf8');
assert.match(app, /createGuestbook\(\{ members, notify, onMottosChange:/, 'Homepage must react to published content from the guestbook bootstrap');
assert.ok(app.includes('guestbook.init()'));
assert.ok(!app.includes('.innerHTML'), 'Visitor input must never be inserted as HTML');
assert.ok(app.includes('prefers-reduced-motion'));
for (const filename of ['public/assets/css/common.css', 'public/assets/js/common.mjs', 'public/assets/css/team.css', 'public/assets/js/app.mjs', 'public/assets/js/motion.mjs', 'public/assets/images/team-group.png', 'public/assets/images/member-closeups.png', 'public/assets/css/worldcup.css', 'public/assets/js/worldcup.mjs', 'public/assets/images/flags.svg']) await access(new URL(filename, root));
assert.ok(html.includes('id="member-watermark">NKDEV</span>'));
assert.ok(app.includes('--face-offset'), 'Member selection must update the close-up panel');
const css = await readFile(new URL('public/assets/css/team.css', root), 'utf8');
assert.match(css, /\.member-face-art \{[^}]*aspect-ratio: 3 \/ 1/);
assert.match(css, /\.member-face-art img \{[^}]*height: auto/);
assert.ok(!css.includes('object-fit: fill'), 'Face artwork must never be stretched to fit');
for (const member of members) {
  const png = await readFile(new URL(`public/${member.portrait}`, root));
  assert.equal(png.subarray(1, 4).toString(), 'PNG');
  assert.equal(png[24], 8, 'Portraits must use 8-bit channels');
  assert.equal(png[25], 6, 'Portraits must retain RGBA transparency');
  assert.ok(png.readUInt32BE(20) > png.readUInt32BE(16), 'Portraits must use a vertical canvas');
  const imageChunks = [];
  for (let offset = 8; offset < png.length;) {
    const length = png.readUInt32BE(offset);
    const type = png.subarray(offset + 4, offset + 8).toString();
    if (type === 'IDAT') imageChunks.push(png.subarray(offset + 8, offset + 8 + length));
    offset += length + 12;
  }
  const pixels = inflateSync(Buffer.concat(imageChunks));
  // The first RGBA pixel follows the filter byte and has zero filter neighbors.
  assert.equal(pixels[4], 0, 'The top-left corner must be transparent, not a baked-in backdrop');
}

const matchIds = demoMatches.map((match) => match.id);
assert.equal(new Set(matchIds).size, demoMatches.length);
assert.equal(selectFeaturedMatch(demoMatches).status, 'live');
assert.equal(selectFeaturedMatch(demoMatches.filter((match) => match.status !== 'live')).id, 'demo-02');
assert.equal(selectFeaturedMatch(demoMatches.filter((match) => match.status === 'finished')).id, 'demo-08');
assert.equal(selectFeaturedMatch([]), null);
assert.equal(beijingDate('2026-06-28T22:00:00Z'), '2026-06-29', 'Dates must use Beijing time, not browser time');
assert.equal(filterMatches(demoMatches).length, 6);
assert.equal(filterMatches(demoMatches, { status: 'live' }).length, 1);
assert.equal(filterMatches(demoMatches, { status: 'all' }).length, 8);
assert.equal(filterMatches(demoMatches, { date: '2026-06-29' }).length, 2);
assert.equal(filterMatches(demoMatches, { date: '2099-01-01' }).length, 0);
assert.deepEqual(normalizeSupportChoices(null, matchIds), {});
assert.deepEqual(normalizeSupportChoices(['home'], matchIds), {});
assert.deepEqual(normalizeSupportChoices({ 'demo-01': 'home', 'demo-02': 'away', 'demo-03': 'invalid', unknown: 'home' }, matchIds), { 'demo-01': 'home', 'demo-02': 'away' });
const flags = await readFile(new URL('public/assets/images/flags.svg', root), 'utf8');
for (const match of demoMatches) {
  assert.ok(Number.isFinite(Date.parse(match.kickoff_at)));
  assert.ok(['live', 'scheduled', 'finished'].includes(match.status));
  assert.notEqual(match.home_team.code, match.away_team.code);
  for (const side of ['home', 'away']) {
    assert.ok(flags.includes(`id="${match[`${side}_team`].code}"`));
    assert.ok(Number.isInteger(match[`${side}_support`]) && match[`${side}_support`] >= 0);
    if (match.status === 'scheduled') assert.equal(match[`${side}_score`], null);
    else assert.ok(Number.isInteger(match[`${side}_score`]) && match[`${side}_score`] >= 0);
  }
  for (const choice of [undefined, 'home', 'away']) {
    const stats = supportStats(match, choice);
    assert.equal(stats.homePercent + stats.awayPercent, stats.total ? 100 : 0);
    assert.equal(stats.total, match.home_support + match.away_support + (choice ? 1 : 0));
  }
}
assert.equal(scoreLabel(demoMatches[1]), '— : —');
assert.equal(scoreLabel(demoMatches[0]), '2 : 1');
assert.deepEqual(supportStats(demoMatches[4]), { home: 0, away: 0, total: 0, homePercent: 0, awayPercent: 0 });
const feed = await loadMatchFeed();
assert.equal(feed.code, 'OK');
assert.equal(feed.data.source, 'demo');
assert.equal(feed.data.snapshot_at, DEMO_SNAPSHOT_AT);
const wcHtml = await readFile(new URL('public/worldcup/demo.html', root), 'utf8');
const wcIds = [...wcHtml.matchAll(/\bid="([^"]+)"/g)].map((match) => match[1]);
assert.equal(wcIds.length, new Set(wcIds).size);
assert.ok(wcHtml.includes('并非官方或实时赛况'));
assert.ok(wcHtml.includes('href="../#team"'));
const snapshotHtml = await readFile(new URL('public/worldcup/index.html', root), 'utf8');
assert.ok(snapshotHtml.includes('name="worldcup-mode" content="snapshot"'));
assert.ok(snapshotHtml.includes('href="./demo.html"'));
assert.ok(snapshotHtml.includes('href="../#team"'));
assert.ok(!snapshotHtml.includes('wc-snapshot-disclosure'), 'The removed usage banner must not reappear');
assert.ok(snapshotHtml.includes('非实时数据'), 'Removing the banner must retain snapshot identity in the footer');
assert.equal(normalizeWorldcupTheme('dark'), 'dark');
for (const value of ['light', 'other', '', null, undefined]) assert.equal(normalizeWorldcupTheme(value), 'light');
assert.equal(shouldShowWorldcupEntrance(), true);
assert.equal(shouldShowWorldcupEntrance({ navigationType: 'reload' }), true, 'Reloading the module entry must show its cover again');
const worldcupFrames = worldcupEntranceKeyframes();
assert.equal(WORLDCUP_REVEAL_TIMING.duration, 2400);
assert.ok(WORLDCUP_REVEAL_TIMING.duration > MOTION_TIMINGS.sceneEntrance, 'The World Cup reveal must be slower without changing shared homepage timing');
assert.equal(WORLDCUP_REVEAL_TIMING.easing, 'cubic-bezier(0.65, 0, 0.35, 1)');
assert.deepEqual(worldcupFrames.reveal, [
  { transform: 'translate3d(0, 0, 0)', offset: 0 },
  { transform: 'translate3d(-250px, 0, 0)', offset: .16 },
  { transform: 'translate3d(-1200px, 0, 0)', offset: 1 },
], 'The cover must pull left as horizontal strips, not clip as one flat panel');
assert.equal(worldcupFrames.ball[1].transform, 'translate3d(0, 0, 0)', 'The leading strip must first meet the football');
assert.equal(worldcupFrames.ball[2].transform, 'translate3d(-950px, 0, 0)');
assert.equal(worldcupEntranceStrips(.5)[0].delay, 262 * WORLDCUP_REVEAL_TIMING.duration / 1500, 'Strip offsets must slow down with the football to preserve the stagger');
for (const guideY of [-1, 0, .1, .5, .9, 1, 2]) {
  const strips = worldcupEntranceStrips(guideY);
  assert.equal(strips.length, 9);
  assert.equal(strips[0].top, 0);
  assert.equal(strips.at(-1).bottom, 1);
  assert.equal(strips.filter((strip) => strip.leading).length, 1);
  assert.equal(strips.find((strip) => strip.leading).delay, 0, 'The football must lead the shortest remaining strip');
  assert.ok(new Set(strips.map((strip) => strip.bottom - strip.top)).size > 3, 'Strip heights must be intentionally uneven');
  assert.ok(new Set(strips.map((strip) => strip.delay)).size > 3, 'The reveal edge must remain staggered');
  strips.forEach((strip, index) => {
    assert.ok(strip.bottom > strip.top);
    assert.ok(strip.delay >= 0 && strip.delay < WORLDCUP_REVEAL_TIMING.duration);
    if (index > 0) assert.equal(strip.top, strips[index - 1].bottom, 'Strips must completely cover the entrance');
  });
}
assert.equal(worldcupFrames.incoming[0].transform, 'translate3d(6vw, 0, 0)');
assert.equal(worldcupFrames.title[0].transform, 'translateX(-108%)');
assert.equal(worldcupFrames.copy[0].transform, 'translate3d(-24px, 0, 0)');
for (const options of [{ search: '?view=teams' }, { hash: '#single-performance' }, { navigationType: 'back_forward' }]) {
  assert.equal(shouldShowWorldcupEntrance(options), false, 'Deep links and browser history must remain directly accessible');
}
const themeBootstrap = await readFile(new URL('public/assets/js/worldcup-theme.js', root), 'utf8');
for (const value of ['light', 'dark', 'invalid', null]) {
  const document = { documentElement: { dataset: {} } };
  runInNewContext(themeBootstrap, { document, localStorage: { getItem(key) { assert.equal(key, WORLDCUP_THEME_KEY); return value; } } });
  assert.equal(document.documentElement.dataset.wcTheme, normalizeWorldcupTheme(value));
}
const storageDisabledDocument = { documentElement: { dataset: {} } };
runInNewContext(themeBootstrap, { document: storageDisabledDocument, get localStorage() { throw new Error('Storage is disabled'); } });
assert.equal(storageDisabledDocument.documentElement.dataset.wcTheme, 'light');
const serverLayout = await readFile(new URL('app/worldcup/views/layout.php', root), 'utf8');
for (const filename of ['worldcup-theme.js', 'worldcup-experience.mjs', 'worldcup-experience.css']) {
  assert.ok(snapshotHtml.includes(filename) && serverLayout.includes(filename), 'Static and server panels must share the same theme/entrance assets');
  await access(new URL(`public/assets/${filename.endsWith('.css') ? 'css' : 'js'}/${filename}`, root));
}
for (const filename of ['stadium-night.jpg', 'football-photo.png']) await access(new URL(`public/assets/images/worldcup/${filename}`, root));
const worldcupExperience = await readFile(new URL('public/assets/js/worldcup-experience.mjs', root), 'utf8');
assert.ok(worldcupExperience.includes("'回到首页'"));
assert.ok(!worldcupExperience.includes('重播开场'));
assert.ok(worldcupExperience.includes("choice.title = `${label}主题`"), 'Icon-only choices must retain accessible theme labels and tooltips');
assert.ok(worldcupExperience.includes("overlay.inert = true"), 'Decorative strip copies must never become interactive');
assert.ok(worldcupExperience.includes("surface.querySelectorAll('[id]')"), 'Strip copies must not introduce duplicate IDs');
for (const view of ['matches', 'teams', 'leaders', 'compare', 'team_compare', 'groups']) assert.ok(snapshotHtml.includes(`data-view="${view}"`));
for (const filename of ['public/assets/js/worldcup.mjs', 'public/assets/js/worldcup-snapshot.mjs', 'public/assets/js/worldcup-experience.mjs', 'public/assets/js/common.mjs', 'public/assets/js/guestbook.mjs', 'public/assets/js/workspace.mjs']) {
  assert.ok(!(await readFile(new URL(filename, root), 'utf8')).includes('.innerHTML'));
}

console.log(`Prototype checks passed: team profiles, safe messages, accessible motion, assets, match feed, Beijing dates, fixture filters, support choices, World Cup themes/entrance guards, empty states, and demo labels (${fileURLToPath(root)}).`);
