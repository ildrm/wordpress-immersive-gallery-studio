(() => {
  "use strict";
  const I = window.IGS;
  I.registerTemplate("carousel", (g) => {
    const stage = g.querySelector(".igs-stage");
    let index = 0;
    g.classList.add("igs-enhanced");
    function render(delta = 0) {
      const list = I.items(g);
      index = list.length ? (index + delta + list.length) % list.length : 0;
      const active = list[index];
      stage.style.transform = active
        ? `translateX(${-active.offsetLeft + 24}px)`
        : "";
      I.activeItem(g, active);
      I.setCounter(g, index);
      g.querySelectorAll(".igs-nav").forEach((button) => {
        button.disabled = list.length < 2;
      });
    }
    I.navigation(g, render);
    I.bindDrag(g, stage, null, ({ dx, cancelled }) => {
      if (!cancelled) render(dx < 0 ? 1 : -1);
    });
    I.listen(g, g, "igs:filter", () => {
      index = 0;
      render();
    });
    const resize = new ResizeObserver(() => render());
    resize.observe(g);
    const stop = I.autoplay(
      g,
      () => render(1),
      I.parseSettings(g).autoplay_speed || 4500,
    );
    render();
    return () => {
      resize.disconnect();
      stop();
    };
  });
})();
