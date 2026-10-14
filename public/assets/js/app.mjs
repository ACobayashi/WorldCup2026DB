import { members, safeGithubUrl } from './members.mjs?v=4';
import { createGuestbook } from './guestbook.mjs?v=2';
import { animateMemberEntrance, transitionScenes } from './motion.mjs';

const $ = (id) => document.getElementById(id);
const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
const selector = $('member-selector');
const messageDialog = $('message-dialog');
const worldcupDialog = $('worldcup-dialog');
let selectedIndex = 0;
let toastTimer;
let activeAnimations = [];
const scenes = [$('team'), $('members')];
const navLinks = Array.from(document.querySelectorAll('.main-nav a'));
let activeScene = 'team';
let transitionToken = 0;

function notify(text) {
  clearTimeout(toastTimer);
  $('toast').textContent = text;
  $('toast').classList.add('is-visible');
  toastTimer = setTimeout(() => $('toast').classList.remove('is-visible'), 3500);
}

const guestbook = createGuestbook({ members, notify, onMottosChange: updateMotto });

function makeSelectors() {
  members.forEach((member, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'member-card';
    button.dataset.member = member.id;
    button.setAttribute('aria-label', `查看${member.name}的成员档案`);
    button.setAttribute('aria-pressed', String(index === selectedIndex));
    button.style.setProperty('--card-accent', member.accent);

    const image = document.createElement('span');
    image.className = 'portrait-art card-portrait';
    image.style.setProperty('--portrait-focus', `${member.portraitFocus * 100}%`);
    const art = document.createElement('img');
    art.src = member.portrait;
    art.alt = '';
    image.append(art);

    const info = document.createElement('span');
    info.className = 'member-card-info';
    const number = document.createElement('small');
    number.textContent = `0${index + 1}`;
    const name = document.createElement('strong');
    name.textContent = member.name;
    info.append(number, name);
    button.append(image, info);
    button.addEventListener('click', () => selectMember(index));
    button.addEventListener('keydown', (event) => {
      const offsets = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
      if (event.key in offsets) {
        event.preventDefault();
        selectMember(index + offsets[event.key]);
        selector.children[selectedIndex].focus();
      } else if (event.key === 'Home' || event.key === 'End') {
        event.preventDefault();
        selectMember(event.key === 'Home' ? 0 : members.length - 1);
        selector.children[selectedIndex].focus();
      }
    });
    selector.append(button);

  });
}

function selectMember(index, animate = true) {
  selectedIndex = ((index % members.length) + members.length) % members.length;
  const member = members[selectedIndex];
  const number = String(selectedIndex + 1).padStart(2, '0');
  activeAnimations.forEach((animation) => animation.cancel());
  activeAnimations = [];

  $('member-stage').dataset.member = member.id;
  $('member-stage').style.setProperty('--member-accent', member.accent);
  $('member-name').textContent = member.name;
  $('member-english').textContent = member.english;
  $('member-school').textContent = member.school;
  $('member-role').textContent = member.role;
  $('member-task').textContent = member.task;
  $('member-tags').replaceChildren(...member.tags.map((text) => {
    const tag = document.createElement('span');
    tag.textContent = text;
    return tag;
  }));
  updateMotto();
  $('member-number').textContent = number;
  $('current-member').firstChild.textContent = number;
  $('portrait-label').textContent = `${number} / ${member.english}`;
  $('member-portrait-image').src = member.portrait;
  $('member-face-art').style.setProperty('--face-offset', `${selectedIndex * -100}%`);
  $('member-stage').style.setProperty('--portrait-focus', `${member.portraitFocus * 100}%`);
  $('member-portrait').setAttribute('aria-label', `${member.name}的卡通示意形象`);
  $('open-message').firstChild.textContent = member.gender === 'female' ? '给她留言 ' : '给他留言 ';

  const github = safeGithubUrl(member.github);
  const githubLink = $('member-github');
  githubLink.firstChild.textContent = github ? 'GitHub 主页 ' : 'GitHub 待补充 ';
  githubLink.setAttribute('aria-disabled', String(!github));
  if (github) {
    githubLink.href = github;
    githubLink.target = '_blank';
    githubLink.rel = 'noopener noreferrer';
    githubLink.removeAttribute('tabindex');
  } else {
    githubLink.removeAttribute('href');
    githubLink.tabIndex = -1;
  }

  Array.from(selector.children).forEach((button, buttonIndex) => {
    button.setAttribute('aria-pressed', String(buttonIndex === selectedIndex));
  });
  guestbook.selectMember(member);
  $('member-status').textContent = `${member.name}，${member.school}，${number} / 04。`;
  if (animate) playMemberEntrance();
}

function playMemberEntrance() {
  activeAnimations.forEach((animation) => animation.cancel());
  activeAnimations = animateMemberEntrance({
    portrait: $('member-portrait'),
    backdrop: $('member-face-motion'),
    copy: $('member-copy'),
    reducedMotion: motionPreference.matches,
  });
}

function updateMotto() {
  const member = members[selectedIndex];
  const motto = guestbook.motto(member.id);
  $('member-quote').textContent = `“${motto?.text || member.quote}”`;
  const teamMotto = guestbook.motto('team');
  if (teamMotto) $('team-motto').textContent = `“${teamMotto.text}”`;
}

makeSelectors();
selectMember(0, false);
guestbook.init();
$('view-team-stands').addEventListener('click', () => {
  $('team-stands').scrollIntoView({ behavior: motionPreference.matches ? 'auto' : 'smooth', block: 'start' });
});
$('previous-member').addEventListener('click', () => selectMember(selectedIndex - 1));
$('next-member').addEventListener('click', () => selectMember(selectedIndex + 1));

document.querySelectorAll('[data-worldcup]').forEach((button) => button.addEventListener('click', () => worldcupDialog.showModal()));
document.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => button.closest('dialog').close()));
document.querySelectorAll('dialog').forEach((dialog) => dialog.addEventListener('click', (event) => {
  if (event.target !== dialog) return;
  const bounds = dialog.getBoundingClientRect();
  if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
}));

function updateNavigation() {
  navLinks.forEach((link) => {
    const active = link.hash === `#${activeScene}`;
    link.classList.toggle('is-active', active);
    if (active) link.setAttribute('aria-current', 'location');
    else link.removeAttribute('aria-current');
  });
  document.body.dataset.scene = activeScene;
  $('scene-counter').textContent = activeScene === 'team' ? '01 / 02 · TEAM' : '02 / 02 · MEMBERS';
  $('scene-next').href = activeScene === 'team' ? '#members' : '#team';
  $('scene-next').textContent = activeScene === 'team' ? '成员档案 ↓' : '团队介绍 ↑';
}

function cancelSceneAnimations() {
  scenes.forEach((scene) => (scene.getAnimations?.() || []).forEach((animation) => animation.cancel()));
}

function settleScenes() {
  scenes.forEach((scene) => {
    scene.hidden = scene.id !== activeScene;
    scene.inert = scene.id !== activeScene;
    scene.classList.remove('is-entering');
  });
}

async function showScene(name, { animate = true, record = true } = {}) {
  if (!['team', 'members'].includes(name)) name = 'team';
  if (record && location.hash !== `#${name}`) history.pushState(null, '', `#${name}`);
  if (name === activeScene && document.body.classList.contains('is-ready')) return;

  const token = ++transitionToken;
  const outgoing = $(activeScene);
  const incoming = $(name);
  const moveFocus = outgoing.contains(document.activeElement);
  cancelSceneAnimations();
  activeScene = name;
  document.body.classList.add('is-ready');
  updateNavigation();
  scenes.forEach((scene) => {
    scene.inert = scene !== incoming;
    scene.classList.remove('is-entering');
  });
  incoming.hidden = false;
  incoming.classList.add('is-entering');
  incoming.scrollTop = 0;
  if (moveFocus) $(name === 'team' ? 'team-title' : 'members-title').focus({ preventScroll: true });
  if (name === 'members' && animate) playMemberEntrance();

  if (animate) {
    await transitionScenes({ outgoing, incoming, direction: name === 'members' ? 1 : -1, reducedMotion: motionPreference.matches });
  }
  // A cancelled transition must not hide a newer destination.
  if (token === transitionToken) settleScenes();
}

document.querySelectorAll('a[href="#team"], a[href="#members"], #scene-next').forEach((link) => {
  link.addEventListener('click', (event) => {
    event.preventDefault();
    showScene(link.hash.slice(1));
  });
});
window.addEventListener('hashchange', () => {
  if (location.hash === '#main') {
    $('main').focus({ preventScroll: true });
    return;
  }
  document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
  showScene(location.hash === '#members' ? 'members' : 'team', { record: false });
});
document.querySelector('.skip-link').addEventListener('click', (event) => {
  event.preventDefault();
  $('main').focus({ preventScroll: true });
});
showScene(location.hash === '#members' ? 'members' : 'team', { animate: false, record: false });

function dialogOpen() {
  return messageDialog.open || worldcupDialog.open;
}

function scrollAvailable(scene, delta) {
  return delta > 0
    ? scene.scrollTop + scene.clientHeight < scene.scrollHeight - 2
    : scene.scrollTop > 2;
}

let wheelAmount = 0;
let lastWheel = 0;
let wheelBlockedUntil = 0;
$('main').addEventListener('wheel', (event) => {
  if (dialogOpen() || event.ctrlKey || Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;
  const delta = event.deltaY * (event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? window.innerHeight : 1);
  if (!delta || scrollAvailable($(activeScene), delta)) return;
  event.preventDefault();
  const now = Date.now();
  if (now < wheelBlockedUntil) return;
  const destination = delta > 0 ? 'members' : 'team';
  if (destination === activeScene) return;
  if (now - lastWheel > 220 || Math.sign(wheelAmount) !== Math.sign(delta)) wheelAmount = 0;
  lastWheel = now;
  wheelAmount += delta;
  if (Math.abs(wheelAmount) >= 55) {
    wheelAmount = 0;
    wheelBlockedUntil = now + 1100;
    showScene(destination);
  }
}, { passive: false });

let touchStart;
$('main').addEventListener('touchstart', (event) => {
  if (dialogOpen() || event.target.closest('button, a, input, textarea, select') || event.touches.length !== 1) {
    touchStart = null;
    return;
  }
  touchStart = {
    x: event.touches[0].clientX,
    y: event.touches[0].clientY,
    scene: activeScene,
    canScrollDown: scrollAvailable($(activeScene), 1),
    canScrollUp: scrollAvailable($(activeScene), -1),
  };
}, { passive: true });
$('main').addEventListener('touchend', (event) => {
  if (!touchStart || dialogOpen()) return;
  const touch = event.changedTouches[0];
  const delta = touchStart.y - touch.clientY;
  const horizontal = Math.abs(touchStart.x - touch.clientX);
  const startedAtBoundary = delta > 0 ? !touchStart.canScrollDown : !touchStart.canScrollUp;
  const sameScene = touchStart.scene === activeScene;
  touchStart = null;
  if (sameScene && startedAtBoundary && Math.abs(delta) > 70 && Math.abs(delta) > horizontal * 1.4) {
    showScene(delta > 0 ? 'members' : 'team');
  }
}, { passive: true });
window.addEventListener('keydown', (event) => {
  if (dialogOpen() || event.target.closest('input, textarea, select, [contenteditable="true"]')) return;
  if (!['PageDown', 'PageUp'].includes(event.key)) return;
  const delta = event.key === 'PageDown' ? 1 : -1;
  event.preventDefault();
  if (scrollAvailable($(activeScene), delta)) {
    $(activeScene).scrollBy({ top: delta * $(activeScene).clientHeight * 0.85, behavior: 'auto' });
    return;
  }
  showScene(delta > 0 ? 'members' : 'team');
});
motionPreference.addEventListener('change', () => {
  ++transitionToken;
  cancelSceneAnimations();
  activeAnimations.forEach((animation) => animation.cancel());
  activeAnimations = [];
  settleScenes();
});

const teamSection = document.querySelector('.team-section');
let pointerFrame;
teamSection.addEventListener('pointermove', (event) => {
  if (motionPreference.matches || event.pointerType === 'touch') return;
  cancelAnimationFrame(pointerFrame);
  pointerFrame = requestAnimationFrame(() => {
    const bounds = teamSection.getBoundingClientRect();
    teamSection.style.setProperty('--pointer-x', `${((event.clientX - bounds.left) / bounds.width - 0.5) * 10}px`);
    teamSection.style.setProperty('--pointer-y', `${((event.clientY - bounds.top) / bounds.height - 0.5) * 6}px`);
  });
});
teamSection.addEventListener('pointerleave', () => {
  cancelAnimationFrame(pointerFrame);
  teamSection.style.setProperty('--pointer-x', '0px');
  teamSection.style.setProperty('--pointer-y', '0px');
});
