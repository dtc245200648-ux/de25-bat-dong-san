document.addEventListener('error', function (event) {
  const image = event.target;
  if (image instanceof HTMLImageElement && !image.dataset.fallback) {
    image.dataset.fallback = '1';
    image.src = '/no-image.svg';
  }
}, true);
