/** FOURWARD coursework | Author: 韩羽宸 | Restore shirt-number placeholder on a local portrait error. */
function showPortraitFallback(image) {
  if (!(image instanceof HTMLImageElement) || !image.hasAttribute('data-player-portrait')) return;
  image.hidden = true;
  const placeholder = image.parentElement?.querySelector('.wc-portrait-fallback');
  if (placeholder) placeholder.hidden = false;
}
document.addEventListener('error', (event) => showPortraitFallback(event.target), true);
for (const image of document.querySelectorAll('img[data-player-portrait]')) {
  if (image.complete && image.naturalWidth === 0) showPortraitFallback(image);
}
