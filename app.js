/* ============================================================
   HiveBreak — interactions & the living honeycomb field
   All motion is gated behind prefers-reduced-motion.
   ============================================================ */
(() => {
  'use strict';
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* ---------- year ---------- */
  $('#year').textContent = new Date().getFullYear();

  /* ---------- nav scroll state ---------- */
  const nav = $('#nav');
  const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 24);
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  /* ---------- hero: play on load (above the fold), staggered ---------- */
  const heroSeq = [...$$('.hero__title .line'), ...$$('.hero .hero-anim')];
  if (reduceMotion) {
    heroSeq.forEach(el => el.classList.add('in'));
  } else {
    heroSeq.forEach((el, i) => {
      el.style.animationDelay = 120 + i * 90 + 'ms';
      el.classList.add('in');
    });
  }

  /* ---------- below-the-fold: reveal on scroll, staggered ---------- */
  const reveals = $$('.reveal, .reveal-left, .reveal-right');
  if (reduceMotion || !('IntersectionObserver' in window)) {
    reveals.forEach(el => el.classList.add('in'));
  } else {
    const io = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const section = entry.target.closest('section');
        const group = $$('.reveal, .reveal-left, .reveal-right', section)
          .filter(el => el.closest('section') === section);
        const idx = Math.max(0, group.indexOf(entry.target));
        entry.target.style.animationDelay = Math.min(idx, 6) * 80 + 'ms';
        entry.target.classList.add('in');
        obs.unobserve(entry.target);
      });
    }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });
    reveals.forEach(el => io.observe(el));
  }

  /* ---------- animated stat / result counters ---------- */
  const fmt = (n) => Math.round(n).toString();
  const runCounter = (el) => {
    const target = parseFloat(el.dataset.target ?? el.dataset.count) || 0;
    const prefix = el.dataset.prefix || '';
    const suffix = el.dataset.suffix || '';
    if (reduceMotion) { el.textContent = prefix + fmt(target) + suffix; return; }
    const dur = 1400, t0 = performance.now();
    const tick = (t) => {
      const p = Math.min((t - t0) / dur, 1);
      const eased = 1 - Math.pow(1 - p, 3);
      el.textContent = prefix + fmt(target * eased) + suffix;
      if (p < 1) requestAnimationFrame(tick);
      else el.textContent = prefix + fmt(target) + suffix;
    };
    requestAnimationFrame(tick);
  };
  const counters = $$('[data-count]');
  if ('IntersectionObserver' in window) {
    const cio = new IntersectionObserver((entries, obs) => {
      entries.forEach(e => { if (e.isIntersecting) { runCounter(e.target); obs.unobserve(e.target); } });
    }, { threshold: 0.6 });
    counters.forEach(el => cio.observe(el));
  } else counters.forEach(runCounter);

  /* ---------- magnetic buttons ---------- */
  if (!reduceMotion && window.matchMedia('(pointer:fine)').matches) {
    $$('[data-magnetic]').forEach(btn => {
      const strength = 18;
      btn.addEventListener('pointermove', (e) => {
        const r = btn.getBoundingClientRect();
        const x = (e.clientX - r.left - r.width / 2) / r.width;
        const y = (e.clientY - r.top - r.height / 2) / r.height;
        btn.style.transform = `translate(${x * strength}px, ${y * strength}px)`;
      });
      btn.addEventListener('pointerleave', () => { btn.style.transform = ''; });
    });

    /* card cursor sheen */
    $$('[data-tilt]').forEach(card => {
      card.addEventListener('pointermove', (e) => {
        const r = card.getBoundingClientRect();
        card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
        card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
      });
    });
  }

  /* ============================================================
     The living honeycomb — a pointer-aware hex field with an
     ambient light wave traveling across the comb.
     ============================================================ */
  const canvas = $('#hive');
  const ctx = canvas.getContext('2d');
  let cells = [], W = 0, H = 0, dpr = 1, raf = 0;
  const pointer = { x: -9999, y: -9999, active: false };

  const R = 30;                 // hex circumradius
  const HS = R * Math.sqrt(3);  // horizontal spacing
  const VS = R * 1.5;           // vertical spacing

  function build() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    W = window.innerWidth; H = window.innerHeight;
    canvas.width = W * dpr; canvas.height = H * dpr;
    canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    cells = [];
    const cols = Math.ceil(W / HS) + 2;
    const rows = Math.ceil(H / VS) + 2;
    for (let row = -1; row < rows; row++) {
      for (let col = -1; col < cols; col++) {
        const x = col * HS + (row % 2 ? HS / 2 : 0);
        const y = row * VS;
        // sparse: keep a scattered subset so it reads as a hive, not a wall
        if ((Math.sin(col * 12.9898 + row * 78.233) * 43758.5453 % 1) > 0.42) {
          cells.push({ x, y, base: 0.05 + Math.random() * 0.05, phase: Math.random() * Math.PI * 2 });
        }
      }
    }
  }

  function hexPath(x, y, r) {
    ctx.beginPath();
    for (let i = 0; i < 6; i++) {
      const a = Math.PI / 180 * (60 * i - 90);
      const px = x + r * Math.cos(a), py = y + r * Math.sin(a);
      i ? ctx.lineTo(px, py) : ctx.moveTo(px, py);
    }
    ctx.closePath();
  }

  function frame(t) {
    ctx.clearRect(0, 0, W, H);
    const time = t * 0.001;
    // diagonal traveling light wave
    const waveDir = { x: 0.7, y: 0.7 };
    const wavePos = (time * 90) % (W + H + 600) - 300;

    for (const c of cells) {
      const proj = c.x * waveDir.x + c.y * waveDir.y;
      const dWave = Math.abs(proj - wavePos);
      const waveGlow = Math.max(0, 1 - dWave / 180);

      let glow = 0;
      if (pointer.active) {
        const dx = c.x - pointer.x, dy = c.y - pointer.y;
        const d = Math.hypot(dx, dy);
        glow = Math.max(0, 1 - d / 190);
      }

      const breathe = 0.5 + 0.5 * Math.sin(time * 0.8 + c.phase);
      const a = c.base + breathe * 0.03 + waveGlow * 0.42 + glow * 0.6;
      const lit = waveGlow * 0.7 + glow;

      hexPath(c.x, c.y, R - 3);
      ctx.lineWidth = 1 + lit * 0.8;
      // dim brand gold -> bright highlight (#DEA331 -> #FFF188) as it lights up
      const rC = 222 + lit * 33, gC = 163 + lit * 78, bC = 49 + lit * 87;
      ctx.strokeStyle = `rgba(${rC|0},${gC|0},${bC|0},${Math.min(a, 0.85)})`;
      ctx.stroke();

      if (lit > 0.55) {
        hexPath(c.x, c.y, R - 6);
        ctx.fillStyle = `rgba(222,163,49,${(lit - 0.55) * 0.14})`;
        ctx.fill();
      }
    }
    raf = requestAnimationFrame(frame);
  }

  function staticField() {
    ctx.clearRect(0, 0, W, H);
    for (const c of cells) {
      hexPath(c.x, c.y, R - 3);
      ctx.lineWidth = 1;
      ctx.strokeStyle = `rgba(222,178,90,${c.base + 0.04})`;
      ctx.stroke();
    }
  }

  function start() {
    build();
    if (reduceMotion) { staticField(); return; }
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(frame);
  }

  if (!reduceMotion) {
    window.addEventListener('pointermove', (e) => {
      pointer.x = e.clientX; pointer.y = e.clientY; pointer.active = true;
    }, { passive: true });
    window.addEventListener('pointerleave', () => { pointer.active = false; });
    // pause when tab hidden
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) cancelAnimationFrame(raf);
      else raf = requestAnimationFrame(frame);
    });
  }

  let rt;
  window.addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(start, 160); });
  start();

  // Signals to the independent failsafe (in <head>) that init completed.
  window.__hbReady = true;
})();
