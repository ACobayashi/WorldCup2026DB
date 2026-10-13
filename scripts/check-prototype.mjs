import assert from 'node:assert/strict';
import { readFile, access } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { inflateSync } from 'node:zlib';
import { members, safeGithubUrl } from '../public/assets/js/members.mjs';
import { normalizeMessages, createMessage } from '../public/assets/js/messages.mjs';
import {
  MOTION_TIMINGS,
  animateMemberEntrance,
  memberEntranceKeyframes,
  sceneTransitionKeyframes,
  transitionScenes,
} from '../public/assets/js/motion.mjs';

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
const htmlIds = [...html.matchAll(/\bid="([^"]+)"/g)].map((match) => match[1]);
assert.equal(htmlIds.length, new Set(htmlIds).size, 'HTML IDs must be unique');
for (const id of ['member-selector', 'member-portrait-image', 'member-face-image', 'message-form', 'message-dialog', 'worldcup-dialog']) assert.ok(htmlIds.includes(id));
assert.ok(!html.includes('所属学院'), 'The homepage must not display the removed college-count statistic');
const app = await readFile(new URL('public/assets/js/app.mjs', root), 'utf8');
assert.ok(!app.includes('.innerHTML'), 'Visitor input must never be inserted as HTML');
assert.ok(app.includes('prefers-reduced-motion'));
for (const filename of ['public/assets/css/team.css', 'public/assets/js/app.mjs', 'public/assets/js/motion.mjs', 'public/assets/images/team-group.png', 'public/assets/images/member-closeups.png']) await access(new URL(filename, root));
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

console.log(`Prototype checks passed: four members, safe URLs, messages, scene/member motion, reduced motion, animation cleanup, unique DOM IDs, and assets (${fileURLToPath(root)}).`);
