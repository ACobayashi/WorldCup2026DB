export const personalAssignments = Object.freeze({ 1: 'Web 前端初探', 2: 'Web 前端设计', 3: '开源建站工具初试' });

export function signalTiming(random = Math.random) {
  return { delay: 900 + random() * 3200, duration: 3200 + random() * 4400 };
}

export function createMemberCoursework({ root, catalog = {}, labels = personalAssignments, authenticated = false }) {
  const nodes = root.querySelector('.coursework-nodes');
  const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
  const timers = new Set();
  const animations = new Set();
  let generation = 0;
  let enabled = false;

  function stopSignals() {
    generation++;
    timers.forEach(clearTimeout);
    timers.clear();
    animations.forEach((animation) => animation.cancel());
    animations.clear();
  }

  function startSignals() {
    if (!enabled || preference.matches || document.hidden) return;
    const current = generation;
    for (const frame of root.querySelectorAll('.coursework-frame')) {
      function schedule() {
        if (generation !== current || !frame.isConnected) return;
        const timing = signalTiming();
        const timer = setTimeout(() => {
          timers.delete(timer);
          if (generation !== current || typeof frame.animate !== 'function') return;
          const animation = frame.animate([{ opacity: .2 }, { opacity: .9, offset: .21 }, { opacity: .32, offset: .4 }, { opacity: .75, offset: .71 }, { opacity: .2 }], { duration: timing.duration, easing: 'ease-in-out' });
          animations.add(animation);
          animation.finished.then(() => { animations.delete(animation); animation.cancel(); schedule(); }).catch(() => {});
        }, timing.delay);
        timers.add(timer);
      }
      schedule();
    }
  }

  function synchronizeSignals() {
    stopSignals();
    startSignals();
  }
  preference.addEventListener('change', synchronizeSignals);
  document.addEventListener('visibilitychange', synchronizeSignals);

  function selectMember(member) {
    stopSignals();
    root.dataset.member = member.id;
    root.setAttribute('aria-label', `${member.name}的课程作业`);
    nodes.replaceChildren(...Object.entries(labels).map(([number, title]) => {
      const file = catalog[member.id]?.[number];
      const link = document.createElement('a');
      link.className = 'coursework-node';
      link.href = file ? `download.php?id=${encodeURIComponent(file.id)}` : `coursework.php#${encodeURIComponent(member.id)}`;
      link.setAttribute('aria-label', `${member.name}的${title}，${file ? (authenticated ? '下载作业' : '登录后下载') : '待提交'}`);
      if (file) link.title = file.title;
      const frame = document.createElement('span');
      frame.className = 'coursework-frame';
      frame.setAttribute('aria-hidden', 'true');
      const index = document.createElement('span');
      index.className = 'coursework-index';
      index.textContent = `0${number}`;
      const copy = document.createElement('span');
      copy.className = 'coursework-copy';
      const heading = document.createElement('strong');
      heading.textContent = title;
      const state = document.createElement('small');
      state.textContent = file ? (authenticated ? '下载作业 ↗' : '登录下载 ↗') : '待提交';
      copy.append(heading, state);
      link.append(frame, index, copy);
      return link;
    }));
    startSignals();
  }

  return { selectMember, setActive(value) { enabled = value; synchronizeSignals(); } };
}
