(() => {
  "use strict";
  const templates = new Map(),
    galleries = new Set();
  const motion = matchMedia("(prefers-reduced-motion: reduce)");
  let lightbox,
    owner,
    opener,
    oldOverflow,
    pausedBefore,
    background = [];
  const words = () => window.IGSFront?.strings || {};
  const label = (key, fallback) => words()[key] || fallback;
  const parseSettings = (g) => {
    try {
      return JSON.parse(g.dataset.settings || "{}");
    } catch {
      return {};
    }
  };
  const items = (g) =>
    [...g.querySelectorAll(".igs-item")].filter((item) => !item.hidden);
  const listen = (g, target, name, callback, options = {}) =>
    target.addEventListener(name, callback, {
      ...options,
      signal: g._igsAbort.signal,
    });
  function track(g, event) {
    if (!window.IGSFront || g.closest("[data-igs-preview]")) return;
    const now = Date.now(),
      last = g._igsEvents || (g._igsEvents = {});
    if (last[event] && now - last[event] < 1000) return;
    last[event] = now;
    const body = new URLSearchParams({
      action: "igs_track",
      nonce: IGSFront.nonce,
      gallery: g.dataset.galleryId,
      event,
    });
    fetch(IGSFront.ajaxUrl, {
      method: "POST",
      body,
      credentials: "same-origin",
      keepalive: true,
    }).catch(() => {});
  }
  function closeLightbox() {
    if (!owner) return;
    lightbox.hidden = true;
    document.documentElement.style.overflow = oldOverflow;
    if (pausedBefore === undefined) delete owner.dataset.paused;
    else owner.dataset.paused = pausedBefore;
    background.forEach(([el, inert]) => {
      el.inert = inert;
    });
    background = [];
    const restore = opener;
    owner = null;
    opener = null;
    if (restore?.isConnected) restore.focus({ preventScroll: true });
  }
  function ensureLightbox() {
    if (lightbox) return lightbox;
    lightbox = document.createElement("div");
    lightbox.className = "igs-lightbox";
    lightbox.hidden = true;
    lightbox.setAttribute("role", "dialog");
    lightbox.setAttribute("aria-modal", "true");
    lightbox.setAttribute("aria-label", label("viewer", "Image viewer"));
    lightbox.innerHTML =
      '<div class="igs-lightbox__bar"><a class="igs-lightbox__download" download hidden></a><button class="igs-lightbox__close" type="button">×</button></div><div class="igs-lightbox__body"><img alt=""></div><p class="igs-lightbox__error" role="status" hidden></p><div class="igs-lightbox__caption"></div>';
    lightbox.querySelector(".igs-lightbox__download").textContent = label(
      "download",
      "Download",
    );
    lightbox
      .querySelector("button")
      .setAttribute("aria-label", label("close", "Close"));
    document.body.append(lightbox);
    lightbox.querySelector("button").addEventListener("click", closeLightbox);
    lightbox.querySelector("a").addEventListener("click", () => {
      if (owner) track(owner, "download");
    });
    lightbox.addEventListener("click", (e) => {
      if (
        e.target === lightbox ||
        e.target.classList.contains("igs-lightbox__body")
      )
        closeLightbox();
    });
    const img = lightbox.querySelector("img"),
      error = lightbox.querySelector(".igs-lightbox__error");
    img.addEventListener("error", () => {
      error.textContent = label(
        "imageError",
        "This image could not be loaded.",
      );
      error.hidden = false;
    });
    img.addEventListener("load", () => {
      error.hidden = true;
    });
    return lightbox;
  }
  function openLightbox(item, g) {
    if (owner) closeLightbox();
    const box = ensureLightbox(),
      settings = parseSettings(g);
    owner = g;
    opener = item;
    oldOverflow = document.documentElement.style.overflow;
    pausedBefore = g.dataset.paused;
    const img = box.querySelector("img");
    img.src = item.dataset.full;
    img.alt = item.querySelector("img")?.alt || "";
    box.querySelector(".igs-lightbox__error").hidden = true;
    box.querySelector(".igs-lightbox__caption").textContent = settings.captions
      ? [item.dataset.title, item.dataset.caption].filter(Boolean).join(" — ")
      : "";
    const link = box.querySelector("a");
    link.href = item.dataset.full;
    link.hidden = !settings.download;
    background = [...document.body.children]
      .filter((el) => el !== box)
      .map((el) => [el, el.inert]);
    background.forEach(([el]) => {
      el.inert = true;
    });
    box.hidden = false;
    document.documentElement.style.overflow = "hidden";
    g.dataset.paused = "1";
    box.querySelector("button").focus();
    track(g, "open");
  }
  document.addEventListener("keydown", (e) => {
    if (!owner) return;
    if (e.key === "Escape") {
      e.preventDefault();
      closeLightbox();
    }
    if (e.key === "Tab") {
      const focusable = [...lightbox.querySelectorAll("button,a[href]")].filter(
        (el) => !el.hidden,
      );
      const index = focusable.indexOf(document.activeElement);
      e.preventDefault();
      focusable[
        (index + (e.shiftKey ? -1 : 1) + focusable.length) % focusable.length
      ].focus();
    }
  });
  function setCounter(g, index = 0) {
    const counter = g.querySelector(".igs-counter"),
      count = items(g).length;
    if (counter)
      counter.textContent = count
        ? `${Math.max(0, Math.min(count - 1, index)) + 1} / ${count}`
        : "0 / 0";
    const empty = g.querySelector(".igs-empty");
    if (empty) empty.hidden = count !== 0;
  }
  function activeItem(g, active) {
    g.querySelectorAll(".igs-item").forEach((item) => {
      const fallback =
        g.classList.contains("igs-reduced") &&
        ["ring3d", "book3d"].includes(g.dataset.template);
      const inactive = item.hidden || (!fallback && item !== active);
      item.inert = inactive;
      item.setAttribute("aria-hidden", String(inactive));
      if (parseSettings(g).lightbox) item.tabIndex = inactive ? -1 : 0;
    });
  }
  function bindDrag(g, stage, move, end) {
    let drag;
    listen(g, stage, "pointerdown", (e) => {
      if (
        (g.classList.contains("igs-reduced") &&
          ["ring3d", "book3d"].includes(g.dataset.template)) ||
        drag ||
        e.isPrimary === false ||
        e.button !== 0 ||
        e.target.closest("button,input,a")
      )
        return;
      drag = {
        id: e.pointerId,
        x: e.clientX,
        y: e.clientY,
        time: performance.now(),
        dx: 0,
        moved: false,
      };
    });
    listen(g, stage, "pointermove", (e) => {
      if (!drag || drag.id !== e.pointerId) return;
      const dx = e.clientX - drag.x,
        dy = e.clientY - drag.y;
      if (!drag.moved && Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 8) {
        drag = null;
        return;
      }
      if (!drag.moved && Math.abs(dx) <= 8) return;
      if (!drag.moved) {
        drag.moved = true;
        stage.setPointerCapture(e.pointerId);
      }
      const previous = drag.dx;
      drag.dx = dx;
      g._igsSuppressUntil = performance.now() + 350;
      move?.({
        dx,
        delta: dx - previous,
        velocity: dx / Math.max(1, performance.now() - drag.time),
      });
    });
    const finish = (e, cancelled) => {
      if (!drag || drag.id !== e.pointerId) return;
      const current = drag;
      drag = null;
      if (stage.hasPointerCapture(e.pointerId))
        stage.releasePointerCapture(e.pointerId);
      if (current.moved) {
        g._igsSuppressUntil = performance.now() + 350;
        end?.({
          dx: current.dx,
          velocity: current.dx / Math.max(1, performance.now() - current.time),
          cancelled,
        });
        if (!cancelled) track(g, "interaction");
      }
    };
    listen(g, window, "pointerup", (e) => finish(e, false));
    listen(g, window, "pointercancel", (e) => finish(e, true));
    listen(g, stage, "lostpointercapture", (e) => finish(e, true));
  }
  function navigation(g, go) {
    listen(g, g.querySelector(".igs-next"), "click", () => {
      go(1);
      track(g, "interaction");
    });
    listen(g, g.querySelector(".igs-prev"), "click", () => {
      go(-1);
      track(g, "interaction");
    });
    listen(g, g, "keydown", (e) => {
      if (
        e.target.closest("input,textarea,select") ||
        !["ArrowLeft", "ArrowRight"].includes(e.key)
      )
        return;
      e.preventDefault();
      go(e.key === "ArrowLeft" ? -1 : 1);
      track(g, "interaction");
    });
  }
  function autoplay(g, advance, duration) {
    if (!parseSettings(g).autoplay) return () => {};
    let stopped = false;
    const button = document.createElement("button");
    button.type = "button";
    button.className = "igs-play";
    const update = () => {
      button.textContent = stopped
        ? label("play", "Play")
        : label("pause", "Pause");
      button.setAttribute("aria-pressed", String(stopped));
      button.hidden = motion.matches;
    };
    listen(g, button, "click", () => {
      stopped = !stopped;
      update();
    });
    listen(g, motion, "change", update);
    update();
    g.append(button);
    const timer = setInterval(() => {
      const rect = g.getBoundingClientRect();
      if (
        !stopped &&
        !motion.matches &&
        !document.hidden &&
        !g.dataset.paused &&
        !g.matches(":hover,:focus-within") &&
        rect.bottom > 0 &&
        rect.top < innerHeight &&
        items(g).length > 1
      )
        advance();
    }, duration);
    return () => {
      clearInterval(timer);
      button.remove();
    };
  }
  function initTemplate(g) {
    const init = templates.get(g.dataset.template);
    if (init && !g._igsTemplateReady) {
      g._igsTemplateReady = true;
      g._igsDestroy = init(g);
    }
  }
  function initGallery(g) {
    if (g.dataset.igsCoreReady === "1") {
      initTemplate(g);
      return;
    }
    g.dataset.igsCoreReady = "1";
    g._igsAbort = new AbortController();
    galleries.add(g);
    const updateMotion = () => {
      g.classList.toggle(
        "igs-reduced",
        motion.matches && Boolean(parseSettings(g).reduced_motion_fallback),
      );
      g.dispatchEvent(new Event("igs:motion"));
    };
    listen(g, motion, "change", updateMotion);
    updateMotion();
    g.querySelectorAll(".igs-item").forEach((item) => {
      const open = () => {
        if (
          parseSettings(g).lightbox &&
          !item.hidden &&
          !item.inert &&
          performance.now() > (g._igsSuppressUntil || 0)
        )
          openLightbox(item, g);
      };
      const download = item.querySelector(".igs-item-download");
      if (download) listen(g, download, "click", () => track(g, "download"));
      listen(g, item, "click", open);
      listen(g, item, "keydown", (e) => {
        if (["Enter", " "].includes(e.key)) {
          e.preventDefault();
          g._igsSuppressUntil = 0;
          open();
        }
      });
    });
    const empty = document.createElement("p");
    empty.className = "igs-empty";
    empty.textContent = label("noResults", "No matching images.");
    empty.setAttribute("role", "status");
    empty.hidden = true;
    g.append(empty);
    const search = g.querySelector(".igs-search");
    if (search)
      listen(g, search, "input", () => {
        const needle = search.value.trim().toLocaleLowerCase();
        g.querySelectorAll(".igs-item").forEach((item) => {
          const haystack = [
            item.dataset.title,
            item.dataset.caption,
            item.querySelector("img")?.alt,
          ]
            .join(" ")
            .toLocaleLowerCase();
          item.hidden = Boolean(needle && !haystack.includes(needle));
        });
        setCounter(g);
        g.dispatchEvent(new Event("igs:filter"));
      });
    setCounter(g);
    initTemplate(g);
    track(g, "view");
  }
  const registerTemplate = (name, init) => {
    templates.set(name, init);
    document
      .querySelectorAll(`.igs-gallery[data-template="${name}"]`)
      .forEach(initGallery);
  };
  window.IGS = {
    parseSettings,
    items,
    listen,
    track,
    setCounter,
    activeItem,
    bindDrag,
    navigation,
    autoplay,
    initGallery,
    registerTemplate,
    motion,
  };
  function boot() {
    document.querySelectorAll(".igs-gallery").forEach(initGallery);
    new MutationObserver((records) => {
      for (const g of galleries)
        if (!g.isConnected) {
          if (owner === g) closeLightbox();
          g._igsDestroy?.();
          g._igsAbort.abort();
          galleries.delete(g);
          delete g.dataset.igsCoreReady;
          g._igsTemplateReady = false;
        }
      for (const record of records)
        for (const node of record.addedNodes)
          if (node.nodeType === 1) {
            if (node.matches(".igs-gallery")) initGallery(node);
            node.querySelectorAll(".igs-gallery").forEach(initGallery);
          }
    }).observe(document.body, { childList: true, subtree: true });
  }
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", boot, { once: true });
  else boot();
})();
