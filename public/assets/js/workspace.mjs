const hero = document.querySelector('.workspace-hero');
const toggle = document.getElementById('workspace-motion');
const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
let userPaused = false;
let pointerFrame;

function updateMotion() {
  const moving = !preference.matches && !userPaused;
  document.body.classList.toggle('has-motion', moving);
  toggle.hidden = preference.matches;
  toggle.setAttribute('aria-pressed', String(!moving));
  toggle.setAttribute('aria-label', moving ? '暂停装饰动效' : '开启装饰动效');
  if (!moving) {
    cancelAnimationFrame(pointerFrame);
    hero.style.removeProperty('--stadium-x');
    hero.style.removeProperty('--stadium-y');
  }
}

toggle.addEventListener('click', () => {
  userPaused = !userPaused;
  updateMotion();
});
preference.addEventListener('change', updateMotion);
hero.addEventListener('pointermove', (event) => {
  if (preference.matches || userPaused || event.pointerType === 'touch') return;
  cancelAnimationFrame(pointerFrame);
  pointerFrame = requestAnimationFrame(() => {
    const bounds = hero.getBoundingClientRect();
    hero.style.setProperty('--stadium-x', ((event.clientX - bounds.left) / bounds.width - 0.5) * 8 + 'px');
    hero.style.setProperty('--stadium-y', ((event.clientY - bounds.top) / bounds.height - 0.5) * 6 + 'px');
  });
});
hero.addEventListener('pointerleave', () => {
  cancelAnimationFrame(pointerFrame);
  hero.style.removeProperty('--stadium-x');
  hero.style.removeProperty('--stadium-y');
});
updateMotion();
