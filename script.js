// Year in footer
document.getElementById("year").textContent = new Date().getFullYear();

// Particle field
(() => {
  const canvas = document.getElementById("particles");
  const ctx = canvas.getContext("2d");
  let w, h, particles = [];
  const mouse = { x: 0, y: 0, active: false };

  function resize() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    w = window.innerWidth; h = window.innerHeight;
    canvas.width = w * dpr; canvas.height = h * dpr;
    canvas.style.width = w + "px"; canvas.style.height = h + "px";
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }
  resize();
  window.addEventListener("resize", resize);

  const count = Math.min(120, Math.floor(window.innerWidth / 16));
  const colors = ["34,211,238", "52,224,161", "168,120,255", "238,242,248"];
  particles = Array.from({ length: count }, () => ({
    x: Math.random() * w, y: Math.random() * h,
    vx: (Math.random() - 0.5) * 0.4, vy: (Math.random() - 0.5) * 0.4,
    size: Math.random() * 1.5 + 0.5,
    color: colors[Math.floor(Math.random() * colors.length)],
    alpha: Math.random() * 0.5 + 0.3,
  }));

  window.addEventListener("mousemove", e => { mouse.x = e.clientX; mouse.y = e.clientY; mouse.active = true; });
  window.addEventListener("mouseleave", () => mouse.active = false);

  function tick() {
    ctx.clearRect(0, 0, w, h);
    for (let i = 0; i < particles.length; i++) {
      const p = particles[i];
      p.x += p.vx; p.y += p.vy;
      if (p.x < 0) p.x = w; else if (p.x > w) p.x = 0;
      if (p.y < 0) p.y = h; else if (p.y > h) p.y = 0;

      if (mouse.active) {
        const dx = mouse.x - p.x, dy = mouse.y - p.y;
        const d = Math.hypot(dx, dy);
        if (d < 160 && d > 0) {
          const f = (160 - d) / 160;
          p.vx -= (dx / d) * f * 0.15;
          p.vy -= (dy / d) * f * 0.15;
        }
      }
      p.vx *= 0.99; p.vy *= 0.99;

      ctx.beginPath();
      ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${p.color}, ${p.alpha})`;
      ctx.fill();

      for (let j = i + 1; j < particles.length; j++) {
        const q = particles[j];
        const dx = p.x - q.x, dy = p.y - q.y;
        const d = Math.hypot(dx, dy);
        if (d < 120) {
          ctx.beginPath();
          ctx.strokeStyle = `rgba(34,211,238,${0.12 * (1 - d / 120)})`;
          ctx.lineWidth = 0.5;
          ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y);
          ctx.stroke();
        }
      }
    }
    requestAnimationFrame(tick);
  }
  tick();
})();
