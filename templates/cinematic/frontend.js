(() => {
  "use strict";
  const I = window.IGS;
  I.registerTemplate("cinematic", (g) => {
    let index = 0;
    g.classList.add("igs-enhanced");
    const render = (delta = 0) => {
      const list = I.items(g);
      index = list.length ? (index + delta + list.length) % list.length : 0;
      g.querySelectorAll(".igs-item").forEach((item) =>
        item.classList.toggle("is-active", item === list[index]),
      );
      I.activeItem(g, list[index]);
      I.setCounter(g, index);
      g.querySelectorAll(".igs-nav").forEach((button) => {
        button.disabled = list.length < 2;
      });
    };
    I.navigation(g, render);
    I.bindDrag(g, g.querySelector(".igs-stage"), null, ({ dx, cancelled }) => {
      if (!cancelled) render(dx < 0 ? 1 : -1);
    });
    I.listen(g, g, "igs:filter", () => {
      index = 0;
      render();
    });
    render();
    return I.autoplay(
      g,
      () => render(1),
      I.parseSettings(g).cinematic_duration || 6000,
    );
  });
})();
