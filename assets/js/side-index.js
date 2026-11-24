// BUSWAY side index: collapse, accordion, scroll tracking and progress bar.
(() => {
    const panel = document.getElementById('sideIndex');
    if (!panel) return;

    const root = document.documentElement;
    const openBtn = document.getElementById('sideIndexOpen');
    const closeBtn = document.getElementById('sideIndexClose');
    const backdrop = document.getElementById('sideIndexBackdrop');
    const bar = document.getElementById('sideIndexBar');
    const KEY = 'busway_index_collapsed';
    const mobile = window.matchMedia('(max-width: 991.98px)');

    const isCollapsed = () => root.classList.contains('index-collapsed');
    const sync = () => {
        panel.inert = isCollapsed(); // keeps hidden links out of the tab order
        if (openBtn) openBtn.setAttribute('aria-expanded', String(!isCollapsed()));
    };
    const setCollapsed = (value, save = true) => {
        root.classList.toggle('index-collapsed', value);
        if (save && !mobile.matches) {
            try { localStorage.setItem(KEY, String(value)); } catch (e) { /* private mode */ }
        }
        sync();
    };

    if (closeBtn) closeBtn.addEventListener('click', () => { setCollapsed(true); if (openBtn) openBtn.focus(); });
    if (openBtn) openBtn.addEventListener('click', () => { setCollapsed(false); if (closeBtn) closeBtn.focus(); });
    if (backdrop) backdrop.addEventListener('click', () => { setCollapsed(true, false); if (openBtn) openBtn.focus(); });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobile.matches && !isCollapsed()) {
            setCollapsed(true, false);
            if (openBtn) openBtn.focus();
        }
    });

    // Crossing the phone/desktop breakpoint: phones start closed, desktops restore the saved choice.
    mobile.addEventListener('change', (event) => {
        if (event.matches) { setCollapsed(true, false); return; }
        let saved = null;
        try { saved = localStorage.getItem(KEY); } catch (e) { /* ignore */ }
        setCollapsed(saved === 'true', false);
    });

    // Accordion groups
    panel.querySelectorAll('.si-group-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const open = button.closest('.si-group').classList.toggle('is-open');
            button.setAttribute('aria-expanded', String(open));
        });
    });

    // Close the drawer after choosing a link on phones
    panel.querySelectorAll('.si-link').forEach((link) => {
        link.addEventListener('click', () => { if (mobile.matches) setCollapsed(true, false); });
    });

    // Scroll tracking: highlight the current section, mark the ones already passed
    const targets = [];
    panel.querySelectorAll('a.si-link[data-spy]').forEach((link) => {
        const el = document.getElementById(link.dataset.spy);
        if (!el) { link.closest('.si-item').hidden = true; return; } // section not on this page
        targets.push({ link, el });
    });

    let queued = false;
    const update = () => {
        queued = false;
        const line = window.innerHeight * 0.35;
        const doc = document.documentElement;
        let current = -1;
        targets.forEach((t, i) => { if (t.el.getBoundingClientRect().top <= line) current = i; });
        if (targets.length && window.innerHeight + window.scrollY >= doc.scrollHeight - 4) current = targets.length - 1;

        targets.forEach((t, i) => {
            t.link.classList.toggle('is-active', i === current);
            t.link.classList.toggle('is-passed', i < current);
            if (i === current) t.link.setAttribute('aria-current', 'location');
            else t.link.removeAttribute('aria-current');
        });

        if (bar) {
            const max = doc.scrollHeight - window.innerHeight;
            bar.style.width = (max > 0 ? Math.min(100, (window.scrollY / max) * 100) : 0) + '%';
        }
    };
    const queue = () => { if (!queued) { queued = true; requestAnimationFrame(update); } };

    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue);
    window.addEventListener('load', queue);
    sync();
    update();
})();