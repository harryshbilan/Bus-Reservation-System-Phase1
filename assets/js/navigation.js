(() => {
  const drawer = document.getElementById('busway-menu');
  const openButton = document.getElementById('busway-menu-open');
  const closeButton = document.getElementById('busway-menu-close');
  if (!drawer || !openButton || !closeButton) return;
  const close = () => { if (drawer.open) drawer.close(); };
  openButton.addEventListener('click', () => {
    if (drawer.open) return;
    drawer.showModal();
    document.body.classList.add('bw-menu-open');
    openButton.setAttribute('aria-expanded', 'true');
    closeButton.focus();
  });
  closeButton.addEventListener('click', close);
  drawer.addEventListener('close', () => {
    document.body.classList.remove('bw-menu-open');
    openButton.setAttribute('aria-expanded', 'false');
    openButton.focus();
  });
  let beganOutside = false;
  const outside = event => {
    const r = drawer.getBoundingClientRect();
    return event.clientX < r.left || event.clientX > r.right ||
      event.clientY < r.top || event.clientY > r.bottom;
  };
  drawer.addEventListener('pointerdown', event => { beganOutside = outside(event); });
  drawer.addEventListener('click', event => {
    if (beganOutside && outside(event)) close();
    beganOutside = false;
  });
  drawer.querySelectorAll('a').forEach(link => {
    if (new URL(link.href).pathname === location.pathname) link.setAttribute('aria-current', 'page');
    link.addEventListener('click', close);
  });
  // Native modal dialog keeps keyboard focus inside and handles Escape.
  window.addEventListener('pageshow', close);
})();