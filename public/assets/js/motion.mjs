export const MOTION_TIMINGS = Object.freeze({
  portrait: 1320,
  backdrop: 1920,
  copy: 460,
  sceneExit: 520,
  sceneEntrance: 780,
});

export const MOTION_EASINGS = Object.freeze({
  braking: 'cubic-bezier(0.08, 0.82, 0.16, 1)',
  backdrop: 'cubic-bezier(0.16, 0.62, 0.18, 1)',
  copy: 'cubic-bezier(0.2, 0.8, 0.2, 1)',
  scene: 'cubic-bezier(0.22, 0.75, 0.18, 1)',
});

export function memberEntranceKeyframes() {
  return {
    portrait: [
      { opacity: 0, transform: 'translate3d(-112px, 0, 0)' },
      { opacity: 1, transform: 'translate3d(0, 0, 0)' },
    ],
    backdrop: [
      { opacity: 0, transform: 'translate3d(86px, 0, 0)' },
      { opacity: 1, transform: 'translate3d(0, 0, 0)' },
    ],
    copy: [
      { opacity: 0, transform: 'translate3d(0, 9px, 0)' },
      { opacity: 1, transform: 'translate3d(0, 0, 0)' },
    ],
  };
}

export function sceneTransitionKeyframes(direction = 1) {
  const sign = direction < 0 ? -1 : 1;
  return {
    outgoing: [
      { opacity: 1, transform: 'translate3d(0, 0, 0) scale(1)' },
      { opacity: 0, transform: `translate3d(0, ${-sign * 6}vh, 0) scale(0.99)` },
    ],
    incoming: [
      { opacity: 0, transform: `translate3d(0, ${sign * 9}vh, 0) scale(1.025)` },
      { opacity: 1, transform: 'translate3d(0, 0, 0) scale(1)' },
    ],
  };
}

function startAnimation(element, keyframes, options) {
  if (typeof element?.animate !== 'function') return null;
  try {
    const animation = element.animate(keyframes, options);
    // Rapid member selections reject finished promises when previous motion is cancelled.
    animation.finished.catch(() => {});
    return animation;
  } catch {
    return null;
  }
}

export function animateMemberEntrance({ portrait, backdrop, copy, reducedMotion = false }) {
  if (reducedMotion) return [];
  const frames = memberEntranceKeyframes();
  return [
    startAnimation(portrait, frames.portrait, {
      duration: MOTION_TIMINGS.portrait,
      easing: MOTION_EASINGS.braking,
    }),
    startAnimation(backdrop, frames.backdrop, {
      duration: MOTION_TIMINGS.backdrop,
      easing: MOTION_EASINGS.backdrop,
    }),
    startAnimation(copy, frames.copy, {
      duration: MOTION_TIMINGS.copy,
      easing: MOTION_EASINGS.copy,
    }),
  ].filter(Boolean);
}

// The caller shows incoming before this call and hides outgoing after it resolves.
export async function transitionScenes({ outgoing, incoming, direction = 1, reducedMotion = false }) {
  if (reducedMotion || outgoing === incoming) return { animated: false, cancelled: false };
  const frames = sceneTransitionKeyframes(direction);
  const animations = [
    startAnimation(outgoing, frames.outgoing, {
      duration: MOTION_TIMINGS.sceneExit,
      easing: MOTION_EASINGS.scene,
      fill: 'both',
    }),
    startAnimation(incoming, frames.incoming, {
      duration: MOTION_TIMINGS.sceneEntrance,
      easing: MOTION_EASINGS.scene,
      fill: 'both',
    }),
  ].filter(Boolean);

  try {
    const results = await Promise.all(animations.map((animation) =>
      animation.finished.then(() => true, () => false)));
    return { animated: animations.length > 0, cancelled: results.includes(false) };
  } finally {
    animations.forEach((animation) => animation.cancel());
  }
}
