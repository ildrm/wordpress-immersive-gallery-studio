(() => {
  "use strict";
  const I = window.IGS;
  I.registerTemplate("justified", (g) => {
    const stage = g.querySelector(".igs-stage"),
      settings = I.parseSettings(g);
    function layout() {
      const list = I.items(g),
        width = stage.clientWidth,
        gap = settings.gap,
        target = settings.thumb_height;
      if (!width) return;
      let row = [],
        ratio = 0;
      const flush = (last) => {
        const available = Math.max(1, width - gap * (row.length - 1));
        const height = last
          ? Math.min(target, available / ratio)
          : available / ratio;
        row.forEach(([item, aspect]) => {
          item.style.width = `${aspect * height}px`;
          item.style.height = `${height}px`;
        });
        row = [];
        ratio = 0;
      };
      list.forEach((item, index) => {
        const aspect = Math.max(
          0.1,
          Math.min(10, (+item.dataset.w || 4) / (+item.dataset.h || 3)),
        );
        row.push([item, aspect]);
        ratio += aspect;
        if (ratio * target + gap * (row.length - 1) >= width) flush(false);
        else if (index === list.length - 1) flush(true);
      });
    }
    const resize = new ResizeObserver(layout);
    resize.observe(stage);
    I.listen(g, g, "igs:filter", layout);
    layout();
    return () => resize.disconnect();
  });
})();
