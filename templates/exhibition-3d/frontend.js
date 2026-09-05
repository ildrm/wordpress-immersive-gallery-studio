(() => {
  "use strict";
  const I = window.IGS;
  I.registerTemplate("museum3d", (g) => {
    const stage = g.querySelector(".igs-stage");
    let rotation = 0;
    const render = () => {
      stage.style.transform = I.motion.matches
        ? ""
        : `rotateX(4deg) rotateY(${rotation}deg)`;
    };
    I.bindDrag(
      g,
      stage,
      ({ delta }) => {
        rotation = Math.max(-18, Math.min(18, rotation + delta * 0.03));
        render();
      },
      () => {},
    );
    I.listen(g, g, "igs:motion", render);
    render();
  });
})();
