(() => {
  "use strict";
  const I = window.IGS;
  I.registerTemplate("ring3d", (g) => {
    const stage = g.querySelector(".igs-stage"),
      settings = I.parseSettings(g);
    let rotation = 0,
      velocity = 0,
      frame = 0,
      lastTime = 0;
    g.classList.add("igs-enhanced");
    function layout() {
      const list = I.items(g),
        radius = Math.min(
          settings.radius,
          Math.max(80, (g.clientWidth - settings.card_width) / 2),
        );
      list.forEach((item, i) => {
        const angle =
          list.length > 1 ? (i / list.length) * Math.PI * 2 + rotation : 0;
        item.style.transform = `translate3d(${Math.sin(angle) * radius}px,0,${(Math.cos(angle) - 1) * radius}px) rotateY(${angle}rad)`;
      });
      const index = list.length
        ? ((Math.round(-rotation / ((Math.PI * 2) / list.length)) %
            list.length) +
            list.length) %
          list.length
        : 0;
      I.activeItem(g, list[index]);
      I.setCounter(g, index);
      g.querySelectorAll(".igs-nav").forEach((button) => {
        button.disabled = list.length < 2;
      });
    }
    function tick(now) {
      frame = 0;
      if (document.hidden || g.dataset.paused || I.motion.matches) {
        velocity = 0;
        return;
      }
      const dt = Math.min(2, (now - (lastTime || now - 16.67)) / 16.67);
      lastTime = now;
      rotation += velocity * dt;
      velocity *= Math.pow(settings.inertia, dt);
      layout();
      if (Math.abs(velocity) > 0.0001) frame = requestAnimationFrame(tick);
    }
    I.bindDrag(
      g,
      stage,
      ({ delta }) => {
        cancelAnimationFrame(frame);
        frame = 0;
        rotation += delta * settings.rotation_speed;
        velocity = I.motion.matches ? 0 : delta * settings.rotation_speed;
        layout();
      },
      ({ cancelled }) => {
        if (cancelled || I.motion.matches) velocity = 0;
        if (velocity) {
          lastTime = 0;
          frame = requestAnimationFrame(tick);
        }
      },
    );
    I.navigation(g, (delta) => {
      cancelAnimationFrame(frame);
      velocity = 0;
      const count = I.items(g).length;
      if (count) rotation -= (delta * Math.PI * 2) / count;
      layout();
    });
    I.listen(g, g, "igs:filter", () => {
      cancelAnimationFrame(frame);
      velocity = 0;
      rotation = 0;
      layout();
    });
    I.listen(g, g, "igs:motion", () => layout());
    const resize = new ResizeObserver(layout);
    resize.observe(g);
    layout();
    return () => {
      cancelAnimationFrame(frame);
      resize.disconnect();
    };
  });
})();
