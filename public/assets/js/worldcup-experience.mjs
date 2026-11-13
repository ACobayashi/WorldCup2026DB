import { MOTION_EASINGS, MOTION_TIMINGS } from './motion.mjs';

export const WORLDCUP_THEME_KEY = 'fourward.worldcup.theme';
export const WORLDCUP_REVEAL_TIMING = Object.freeze({
  duration: 2400,
  easing: 'cubic-bezier(0.65, 0, 0.35, 1)',
});

export function normalizeWorldcupTheme(value) {
  return value === 'dark' ? 'dark' : 'light';
}

export function shouldShowWorldcupEntrance({ search = '', hash = '', navigationType = 'navigate' } = {}) {
  return !search && !hash && navigationType !== 'back_forward';
}

export function worldcupEntranceStrips(guideY = .5) {
  const edges = [0, .12, .205, .335, .405, .535, .625, .755, .845, 1];
  const position = Math.max(0, Math.min(.999, guideY));
  const leadingIndex = edges.findIndex((edge, index) => position >= edge && position < edges[index + 1]);
  const stagger = [86, 8, 112, 20, 74, 56, 98, 10, 124];
  return edges.slice(0, -1).map((top, index) => ({
    top,
    bottom: edges[index + 1],
    leading: index === leadingIndex,
    delay: index === leadingIndex ? 0 : (Math.abs(index - leadingIndex) * 44 + stagger[index]) * WORLDCUP_REVEAL_TIMING.duration / 1500,
  }));
}

export function worldcupEntranceKeyframes({ width = 1000, guideX = 750, ballSize = 200 } = {}) {
  return {
    reveal: [
      { transform: 'translate3d(0, 0, 0)', offset: 0 },
      { transform: `translate3d(${guideX - width}px, 0, 0)`, offset: .16 },
      { transform: `translate3d(${-width - ballSize}px, 0, 0)`, offset: 1 },
    ],
    ball: [
      { transform: 'translate3d(0, 0, 0)', offset: 0 },
      { transform: 'translate3d(0, 0, 0)', offset: .16 },
      { transform: `translate3d(${-guideX - ballSize}px, 0, 0)`, offset: 1 },
    ],
    incoming: [
      { opacity: 0, transform: 'translate3d(6vw, 0, 0)' },
      { opacity: 1, transform: 'translate3d(0, 0, 0)' },
    ],
    title: [
      { transform: 'translateX(-108%)' },
      { transform: 'translateX(0)' },
    ],
    copy: [
      { opacity: 0, transform: 'translate3d(-24px, 0, 0)' },
      { opacity: 1, transform: 'translate3d(0, 0, 0)' },
    ],
  };
}

function element(tag, className, text) {
  const item = document.createElement(tag);
  if (className) item.className = className;
  if (text) item.textContent = text;
  return item;
}

function button(className, text, action) {
  const item = element('button', className, text);
  item.type = 'button';
  item.addEventListener('click', action);
  return item;
}

function themeSwitch(setTheme) {
  const group = element('div', 'wc-theme-switch');
  group.setAttribute('role', 'group');
  group.setAttribute('aria-label', '世界杯配色');
  for (const [theme, label] of [['dark', '深色'], ['light', '蓝色']]) {
    const choice = button('', '', () => setTheme(theme));
    choice.dataset.wcThemeChoice = theme;
    choice.setAttribute('aria-label', `${label}主题`);
    choice.title = `${label}主题`;
    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('aria-hidden', 'true');
    icon.setAttribute('fill', 'none');
    icon.setAttribute('stroke', 'currentColor');
    icon.setAttribute('stroke-width', '1.6');
    icon.setAttribute('stroke-linecap', 'round');
    icon.setAttribute('stroke-linejoin', 'round');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', theme === 'dark'
      ? 'M20.5 14A8.5 8.5 0 0 1 10 3.5 8.5 8.5 0 1 0 20.5 14Z'
      : 'M12 2v2m0 16v2M2 12h2m16 0h2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42');
    icon.append(path);
    if (theme === 'light') {
      const center = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      center.setAttribute('cx', '12');
      center.setAttribute('cy', '12');
      center.setAttribute('r', '4');
      icon.append(center);
    }
    choice.append(icon);
    group.append(choice);
  }
  return group;
}

function createEntrance(setTheme, closeEntrance) {
  const entrance = element('section', 'wc-entrance');
  entrance.hidden = true;
  entrance.setAttribute('aria-labelledby', 'wc-entrance-title');
  const background = element('div', 'wc-entrance-background');
  background.setAttribute('aria-hidden', 'true');
  const photo = element('img', 'wc-entrance-photo');
  photo.src = new URL('../images/worldcup/stadium-night.jpg', import.meta.url).href;
  photo.alt = '';
  photo.width = 1738;
  photo.height = 600;
  background.append(photo, element('div', 'wc-entrance-shade'), element('div', 'wc-entrance-grid'));

  const inner = element('div', 'wc-entrance-inner');
  const top = element('div', 'wc-entrance-top');
  const controls = element('div', 'wc-entrance-controls');
  controls.append(button('wc-text-button wc-entrance-skip', '跳过开场 ↗', () => closeEntrance(true)), themeSwitch(setTheme));
  top.append(element('span', 'wc-entrance-index', '03 / OUR WORLD STAGE'), controls);

  const stage = element('div', 'wc-entrance-stage');
  const copy = element('div', 'wc-entrance-copy');
  const eyebrow = element('p', 'wc-entrance-eyebrow', 'FOURWARD / WORLD CUP 2026');
  const title = element('h2', 'wc-entrance-title');
  title.id = 'wc-entrance-title';
  title.tabIndex = -1;
  for (const line of ['世界同场', '热爱开场。']) {
    const mask = element('span', 'wc-entrance-line');
    mask.append(element('span', '', line));
    title.append(mask);
  }
  const description = element('p', 'wc-entrance-description', '从四人同行，到世界同场。\n赛程、球队与球员，探索我们的世界杯主场。');
  const enter = button('glass-button glass-button--primary wc-entrance-enter', '进入世界杯面板', () => closeEntrance(false));
  const arrow = element('span', '', '↗');
  arrow.setAttribute('aria-hidden', 'true');
  enter.append(arrow);
  const topics = element('p', 'wc-entrance-topics', '赛程 / 球队 / 数据 / 晋级');
  copy.append(eyebrow, title, description, enter, topics);

  const art = element('div', 'wc-entrance-art');
  art.setAttribute('aria-hidden', 'true');
  const year = element('span', 'wc-entrance-year', '2026');
  const orbit = element('div', 'wc-entrance-orbit');
  const ball = element('img', 'wc-entrance-ball');
  ball.src = new URL('../images/worldcup/football-photo.png', import.meta.url).href;
  ball.alt = '';
  ball.width = 447;
  ball.height = 447;
  const artCaption = element('div', 'wc-entrance-art-caption');
  artCaption.append(element('span', '', 'THE WORLD’S GAME'), element('span', '', '[ 2026 / TOGETHER ]'));
  art.append(year, orbit, ball, artCaption);
  stage.append(copy, art);

  const bottom = element('div', 'wc-entrance-bottom');
  const credit = element('a', 'wc-entrance-credit', '球场摄影 Pure-football / CC BY-SA 3.0 ↗');
  credit.href = 'https://commons.wikimedia.org/wiki/File:Campnou_3.jpg';
  credit.target = '_blank';
  credit.rel = 'noopener noreferrer';
  bottom.append(credit);
  inner.append(top, stage, bottom);
  entrance.append(background, inner);
  return entrance;
}

function createEntrancePull(entrance) {
  const bounds = entrance.getBoundingClientRect();
  const originalBall = entrance.querySelector('.wc-entrance-ball');
  const ballBounds = originalBall.getBoundingClientRect();
  const size = Math.min(originalBall.offsetWidth, bounds.height * .4);
  const guideX = ballBounds.left - bounds.left + ballBounds.width / 2;
  const guideY = Math.max(size / 2, Math.min(bounds.height - size / 2, ballBounds.top - bounds.top + ballBounds.height / 2));
  const overlay = element('div', 'wc-entrance-pull');
  overlay.setAttribute('aria-hidden', 'true');
  overlay.inert = true;
  Object.assign(overlay.style, {
    top: `${bounds.top}px`, left: `${bounds.left}px`, width: `${bounds.width}px`, height: `${bounds.height}px`,
  });
  const strips = worldcupEntranceStrips(guideY / bounds.height).map((layout) => {
    const strip = element('div', 'wc-entrance-strip');
    strip.dataset.leading = String(layout.leading);
    strip.style.top = `${layout.top * bounds.height}px`;
    // A subpixel overlap prevents seams between independently composited strips.
    strip.style.height = `${(layout.bottom - layout.top) * bounds.height + .75}px`;
    const surface = entrance.cloneNode(true);
    surface.hidden = false;
    surface.classList.add('wc-entrance-strip-surface');
    surface.removeAttribute('aria-labelledby');
    surface.querySelectorAll('[id]').forEach((item) => item.removeAttribute('id'));
    surface.querySelector('.wc-entrance-ball').remove();
    Object.assign(surface.style, { top: `${-layout.top * bounds.height}px`, height: `${bounds.height}px` });
    strip.append(surface);
    overlay.append(strip);
    return { strip, surface, ...layout };
  });
  const guide = element('div', 'wc-entrance-pull-guide');
  Object.assign(guide.style, {
    left: `${guideX - size / 2}px`, top: `${guideY - size / 2}px`, width: `${size}px`, height: `${size}px`,
  });
  const ball = entrance.querySelector('.wc-entrance-ball').cloneNode(true);
  ball.className = 'wc-entrance-pull-ball';
  guide.append(ball);
  overlay.append(guide);
  document.body.append(overlay);
  strips.forEach(({ surface }) => { surface.scrollTop = entrance.scrollTop; });
  return { overlay, strips, guide, ball, frames: worldcupEntranceKeyframes({ width: bounds.width, guideX, ballSize: size }) };
}

export function initializeWorldcupExperience() {
  const main = document.querySelector('.wc-main');
  const headingActions = document.querySelector('.wc-heading-actions');
  if (!main || !headingActions) return;

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const frames = worldcupEntranceKeyframes();
  const footer = document.querySelector('.site-footer');
  const animations = new Set();
  let active = false;
  let leaving = false;
  let previousFocus = null;
  let previousScroll = 0;
  let previousInert = [];

  function setTheme(value, persist = true) {
    const theme = normalizeWorldcupTheme(value);
    document.documentElement.dataset.wcTheme = theme;
    document.querySelectorAll('[data-wc-theme-choice]').forEach((choice) => {
      choice.setAttribute('aria-pressed', String(choice.dataset.wcThemeChoice === theme));
    });
    if (persist) {
      try { localStorage.setItem(WORLDCUP_THEME_KEY, theme); } catch { /* Theme switching still works in memory. */ }
    }
  }

  function animate(target, frames, options) {
    if (reducedMotion.matches || typeof target?.animate !== 'function') return null;
    try {
      const animation = target.animate(frames, { easing: MOTION_EASINGS.scene, ...options });
      animations.add(animation);
      animation.finished.catch(() => {}).finally(() => {
        animation.cancel();
        animations.delete(animation);
      });
      return animation;
    } catch {
      return null;
    }
  }

  function cancelAnimations() {
    animations.forEach((animation) => animation.cancel());
    animations.clear();
  }

  async function closeEntrance(skip = false) {
    if (!active) return;
    if (leaving) {
      if (skip) cancelAnimations();
      return;
    }
    leaving = true;
    cancelAnimations();
    let pull = null;
    try {
      if (!skip && !reducedMotion.matches) {
        pull = createEntrancePull(entrance);
        entrance.classList.add('wc-entrance--pulling');
        const reveals = pull.strips.map(({ strip, delay }) => animate(strip, pull.frames.reveal, {
          ...WORLDCUP_REVEAL_TIMING, delay, duration: WORLDCUP_REVEAL_TIMING.duration - delay, fill: 'both',
        }));
        reveals.push(animate(pull.guide, pull.frames.ball, { ...WORLDCUP_REVEAL_TIMING, fill: 'both' }));
        animate(pull.ball, [{ rotate: '-18deg' }, { rotate: '-558deg' }], WORLDCUP_REVEAL_TIMING);
        animate(main, frames.incoming, WORLDCUP_REVEAL_TIMING);
        await Promise.all(reveals.filter(Boolean).map((animation) => animation.finished.catch(() => {})));
      }
    } finally {
      cancelAnimations();
      pull?.overlay.remove();
      entrance.classList.remove('wc-entrance--pulling');
      entrance.hidden = true;
      document.body.classList.remove('wc-entrance-open');
      previousInert.forEach(([target, inert]) => { target.inert = inert; });
      active = false;
      leaving = false;
      window.scrollTo(0, previousScroll);
      (previousFocus?.isConnected ? previousFocus : main).focus({ preventScroll: true });
    }
  }

  const entrance = createEntrance(setTheme, closeEntrance);
  main.before(entrance);
  const tools = element('div', 'wc-experience-tools');
  const replay = button('wc-text-button wc-replay', '回到首页', () => openEntrance(true));
  replay.setAttribute('aria-label', '回到世界杯首页');
  tools.append(replay, themeSwitch(setTheme));
  headingActions.append(tools);
  setTheme(document.documentElement.dataset.wcTheme, false);

  function openEntrance(replaying = false) {
    if (active) return;
    active = true;
    previousFocus = replaying ? document.activeElement : null;
    previousScroll = window.scrollY;
    previousInert = [main, footer].filter(Boolean).map((target) => [target, target.inert]);
    previousInert.forEach(([target]) => { target.inert = true; });
    window.scrollTo(0, 0);
    entrance.hidden = false;
    document.body.classList.add('wc-entrance-open');
    entrance.scrollTop = 0;
    entrance.querySelector('.wc-entrance-title').focus({ preventScroll: true });
    animate(entrance.querySelector('.wc-entrance-photo'), [
      { opacity: 0, transform: 'scale(1.14)' },
      { opacity: 1, transform: 'scale(1)' },
    ], { duration: MOTION_TIMINGS.backdrop, easing: MOTION_EASINGS.backdrop });
    animate(entrance.querySelector('.wc-entrance-art'), [
      { opacity: 0, transform: 'translate3d(86px, 0, 0) rotate(4deg)' },
      { opacity: 1, transform: 'translate3d(0, 0, 0) rotate(0)' },
    ], { duration: MOTION_TIMINGS.portrait, easing: MOTION_EASINGS.braking });
    entrance.querySelectorAll('.wc-entrance-line > span').forEach((line, index) => {
      animate(line, frames.title, {
        duration: 1050, delay: 100 + index * 130, fill: 'backwards', easing: MOTION_EASINGS.braking,
      });
    });
    entrance.querySelectorAll('.wc-entrance-eyebrow, .wc-entrance-description, .wc-entrance-enter, .wc-entrance-topics, .wc-entrance-bottom').forEach((item, index) => {
      animate(item, frames.copy, {
        duration: 700, delay: 180 + index * 90, fill: 'backwards', easing: MOTION_EASINGS.copy,
      });
    });
  }

  document.addEventListener('keydown', (event) => {
    if (active && event.key === 'Escape') {
      event.preventDefault();
      closeEntrance(true);
    }
  });
  document.querySelector('.skip-link')?.addEventListener('click', () => { if (active) closeEntrance(true); });
  reducedMotion.addEventListener('change', () => { if (reducedMotion.matches) cancelAnimations(); });
  window.addEventListener('storage', (event) => {
    if (event.key === WORLDCUP_THEME_KEY || event.key === null) setTheme(event.newValue, false);
  });

  if (shouldShowWorldcupEntrance({
    search: location.search, hash: location.hash,
    navigationType: performance.getEntriesByType('navigation')[0]?.type ?? 'navigate',
  })) openEntrance();
}

if (typeof document !== 'undefined') initializeWorldcupExperience();
