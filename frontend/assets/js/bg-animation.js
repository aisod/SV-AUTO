(function () {
  const canvas = document.createElement('canvas');
  canvas.id = 'bg-canvas';
  Object.assign(canvas.style, {
    position: 'fixed',
    top: '0', left: '0',
    width: '100%', height: '100%',
    zIndex: '0',
    pointerEvents: 'none',
  });
  document.body.prepend(canvas);

  const ctx = canvas.getContext('2d');

  // --- CONFIG ---
  const DOT_SPACING   = 30;
  const DOT_RADIUS    = 1.4;
  const RIPPLE_RADIUS = 130;
  const MAX_SHIFT     = 12;
  const NUM_PARTICLES = 55;
  const CONNECT_DIST  = 140;
  const PARTICLE_SPEED = 0.4;

  let W, H, dots = [], particles = [], raf;
  let mouse = { x: -9999, y: -9999 };
  let ripples = [];

  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
    buildDots();
    buildParticles();
  }

  // ---- DOT GRID ----
  function buildDots() {
    dots = [];
    const cols = Math.ceil(W / DOT_SPACING) + 1;
    const rows = Math.ceil(H / DOT_SPACING) + 1;
    for (let r = 0; r < rows; r++)
      for (let c = 0; c < cols; c++)
        dots.push({ ox: c * DOT_SPACING, oy: r * DOT_SPACING, x: c * DOT_SPACING, y: r * DOT_SPACING });
  }

  function updateDots() {
    for (let i = 0; i < dots.length; i++) {
      const d = dots[i];
      let tx = d.ox, ty = d.oy;

      const mdx = mouse.x - d.ox, mdy = mouse.y - d.oy;
      const mdist = Math.sqrt(mdx * mdx + mdy * mdy);
      if (mdist < RIPPLE_RADIUS) {
        const f = (1 - mdist / RIPPLE_RADIUS);
        const a = Math.atan2(mdy, mdx);
        tx = d.ox - Math.cos(a) * MAX_SHIFT * f * f;
        ty = d.oy - Math.sin(a) * MAX_SHIFT * f * f;
      }

      for (let rp of ripples) {
        const rdx = rp.x - d.ox, rdy = rp.y - d.oy;
        const rdist = Math.sqrt(rdx * rdx + rdy * rdy);
        const diff = Math.abs(rdist - rp.r);
        if (diff < 20) {
          const strength = (1 - diff / 20) * (1 - rp.life) * 8;
          const ang = Math.atan2(rdy, rdx);
          tx += Math.cos(ang) * strength;
          ty += Math.sin(ang) * strength;
        }
      }

      d.x += (tx - d.x) * 0.1;
      d.y += (ty - d.y) * 0.1;
    }
  }

  function drawDots() {
    for (let i = 0; i < dots.length; i++) {
      const d = dots[i];
      const shift = Math.sqrt((d.x - d.ox) ** 2 + (d.y - d.oy) ** 2);
      const alpha = 0.1 + (shift / MAX_SHIFT) * 0.3;
      const r = DOT_RADIUS + (shift / MAX_SHIFT) * 1.2;
      ctx.beginPath();
      ctx.arc(d.x, d.y, r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(0,0,0,${Math.min(alpha, 0.38)})`;
      ctx.fill();
    }
  }

  // ---- RIPPLE WAVES (on click) ----
  function spawnRipple(x, y) {
    ripples.push({ x, y, r: 0, life: 0 });
  }

  function updateRipples() {
    for (let i = ripples.length - 1; i >= 0; i--) {
      ripples[i].r    += 4;
      ripples[i].life += 0.018;
      if (ripples[i].life >= 1) ripples.splice(i, 1);
    }
  }

  function drawRipples() {
    for (let rp of ripples) {
      const alpha = (1 - rp.life) * 0.18;
      ctx.beginPath();
      ctx.arc(rp.x, rp.y, rp.r, 0, Math.PI * 2);
      ctx.strokeStyle = `rgba(0,0,0,${alpha})`;
      ctx.lineWidth = 1.5;
      ctx.stroke();
    }
  }

  // ---- CONSTELLATION PARTICLES ----
  function buildParticles() {
    particles = [];
    for (let i = 0; i < NUM_PARTICLES; i++) {
      particles.push({
        x:  Math.random() * W,
        y:  Math.random() * H,
        vx: (Math.random() - 0.5) * PARTICLE_SPEED,
        vy: (Math.random() - 0.5) * PARTICLE_SPEED,
        r:  Math.random() * 1.5 + 1,
      });
    }
  }

  function updateParticles() {
    for (let p of particles) {
      p.x += p.vx;
      p.y += p.vy;
      if (p.x < 0 || p.x > W) p.vx *= -1;
      if (p.y < 0 || p.y > H) p.vy *= -1;

      const dx = mouse.x - p.x, dy = mouse.y - p.y;
      const dist = Math.sqrt(dx * dx + dy * dy);
      if (dist < 200) {
        p.vx += (dx / dist) * 0.015;
        p.vy += (dy / dist) * 0.015;
      }

      const speed = Math.sqrt(p.vx * p.vx + p.vy * p.vy);
      if (speed > 1.2) { p.vx = (p.vx / speed) * 1.2; p.vy = (p.vy / speed) * 1.2; }
    }
  }

  function drawParticles() {
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const dx = particles[i].x - particles[j].x;
        const dy = particles[i].y - particles[j].y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < CONNECT_DIST) {
          const alpha = (1 - dist / CONNECT_DIST) * 0.2;
          ctx.beginPath();
          ctx.moveTo(particles[i].x, particles[i].y);
          ctx.lineTo(particles[j].x, particles[j].y);
          ctx.strokeStyle = `rgba(0,0,0,${alpha})`;
          ctx.lineWidth = 0.8;
          ctx.stroke();
        }
      }

      const mdx = mouse.x - particles[i].x;
      const mdy = mouse.y - particles[i].y;
      const mdist = Math.sqrt(mdx * mdx + mdy * mdy);
      if (mdist < CONNECT_DIST) {
        const alpha = (1 - mdist / CONNECT_DIST) * 0.25;
        ctx.beginPath();
        ctx.moveTo(particles[i].x, particles[i].y);
        ctx.lineTo(mouse.x, mouse.y);
        ctx.strokeStyle = `rgba(0,0,0,${alpha})`;
        ctx.lineWidth = 0.8;
        ctx.stroke();
      }
    }

    for (let p of particles) {
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(0,0,0,0.25)';
      ctx.fill();
    }
  }

  // ---- MAIN LOOP ----
  function loop() {
    ctx.clearRect(0, 0, W, H);
    updateRipples();
    updateDots();
    drawDots();
    drawRipples();
    updateParticles();
    drawParticles();
    raf = requestAnimationFrame(loop);
  }

  window.addEventListener('mousemove', e => { mouse.x = e.clientX; mouse.y = e.clientY; });
  window.addEventListener('mouseleave', () => { mouse.x = -9999; mouse.y = -9999; });
  window.addEventListener('click', e => spawnRipple(e.clientX, e.clientY));
  window.addEventListener('resize', () => { cancelAnimationFrame(raf); resize(); loop(); });

  resize();
  loop();
})();
