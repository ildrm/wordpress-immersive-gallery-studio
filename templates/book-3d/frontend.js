(() => {
  "use strict";
  const I = window.IGS;
  I.registerTemplate("book3d", (g) => {
    const stage = g.querySelector(".igs-stage"),
      settings = I.parseSettings(g);
    let page = 0;
    g.classList.add("igs-enhanced");
    g.style.setProperty(
      "--igs-turn-time",
      `${150 + settings.page_stiffness * 450}ms`,
    );
    function render(dx = 0) {
      const list = I.items(g);
      page = Math.max(0, Math.min(page, list.length - 1));
      const forward = dx < 0,
        target = forward ? page : page - 1;
      const allowed = forward ? page < list.length - 1 : page > 0;
      const progress = allowed
        ? Math.min(
            1,
            Math.abs(dx) /
              Math.max(
                1,
                Math.min(settings.page_width, stage.clientWidth - 64),
              ),
          )
        : 0;
      list.forEach((item, i) => {
        let angle = i < page ? -179 : 0;
        if (i === target && progress)
          angle = forward ? -179 * progress : -179 + 179 * progress;
        item.style.transform = `translate(-50%, -50%) rotateY(${angle}deg)`;
        item.style.zIndex = String(
          i === target && progress ? list.length + 1 : list.length - i,
        );
        item.style.visibility = i < page - 1 ? "hidden" : "";
      });
      I.activeItem(g, list[page]);
      I.setCounter(g, page);
      g.querySelector(".igs-prev").disabled = page <= 0;
      g.querySelector(".igs-next").disabled = page >= list.length - 1;
    }
    const go = (delta) => {
      page += delta;
      render();
    };
    I.navigation(g, go);
    I.bindDrag(
      g,
      stage,
      ({ dx }) => {
        stage.classList.add("is-dragging");
        render(dx);
      },
      ({ dx, velocity, cancelled }) => {
        stage.classList.remove("is-dragging");
        const width = Math.max(
          1,
          Math.min(settings.page_width, stage.clientWidth - 64),
        );
        if (
          !cancelled &&
          (Math.abs(dx) / width > 0.4 || Math.abs(velocity) > 0.7)
        )
          page += dx < 0 ? 1 : -1;
        render();
      },
    );
    I.listen(g, g, "igs:filter", () => {
      page = 0;
      render();
    });
    I.listen(g, g, "igs:motion", () => render());
    const resize = new ResizeObserver(() => render());
    resize.observe(g);
    render();
    return () => resize.disconnect();
  });
})();
