(($) => {
  "use strict";
  const config = window.IGSAdmin;
  if (!config) return;
  const grid = document.getElementById("igs-media-grid"),
    preview = document.getElementById("igs-preview");
  const builder = document.querySelector(".igs-builder");
  const t = (key, fallback) => config.strings?.[key] || fallback;
  let timer,
    request,
    version = 0;
  document.getElementById('igs-open-builder')?.addEventListener('click', () => {
    const box = document.getElementById('igs_builder');
    const pane = box?.closest('.edit-post-meta-boxes-main');
    pane?.querySelector('button[aria-expanded="false"]')?.click();
    requestAnimationFrame(() => {
      box?.scrollIntoView({block:'start'});
      document.getElementById('igs-add-media')?.focus({preventScroll:true});
    });
  });

  const relevant = {
    columns_desktop: ["grid", "masonry", "collage", "museum3d"],
    columns_tablet: ["grid", "masonry", "collage", "museum3d"],
    columns_mobile: ["grid", "masonry", "collage", "museum3d"],
    thumb_height: ["grid", "justified", "collage", "museum3d"],
    radius: ["ring3d"],
    card_width: ["ring3d"],
    card_height: ["ring3d"],
    rotation_speed: ["ring3d"],
    inertia: ["ring3d"],
    page_width: ["book3d"],
    page_height: ["book3d"],
    page_stiffness: ["book3d"],
    autoplay: ["carousel", "cinematic"],
    autoplay_speed: ["carousel"],
    cinematic_duration: ["cinematic"],
    reduced_motion_fallback: ["ring3d", "book3d"],
  };
  function controls() {
    const template =
      builder?.querySelector('[name="igs_settings[template]"]:checked')
        ?.value || "grid";
    builder?.querySelectorAll(".igs-control,.igs-switch").forEach((control) => {
      const input = control.querySelector("input"),
        key = input?.name.match(/\[([^\]]+)\]/)?.[1];
      control.hidden = Boolean(
        relevant[key] && !relevant[key].includes(template),
      );
    });
    builder
      ?.querySelectorAll(".igs-template-card")
      .forEach((card) =>
        card.classList.toggle(
          "is-active",
          Boolean(card.querySelector("input:checked")),
        ),
      );
  }
  async function refreshPreview() {
    if (!grid || !preview) return;
    controls();
    request?.abort();
    const current = ++version;
    if (!grid.querySelector(".igs-media-item")) {
      preview.textContent = t(
        "addImages",
        "Add images to start building your gallery.",
      );
      return;
    }
    request = new AbortController();
    const body = new URLSearchParams({
      action: "igs_preview",
      nonce: config.previewNonce,
      gallery: builder.dataset.post,
    });
    grid
      .querySelectorAll("input")
      .forEach((input) => body.append("media[]", input.value));
    builder.querySelectorAll('[name^="igs_settings["]').forEach((input) => {
      if (["radio", "checkbox"].includes(input.type) && !input.checked) return;
      body.set(input.name.replace("igs_settings[", "settings["), input.value);
    });
    preview.setAttribute("aria-busy", "true");
    try {
      const response = await fetch(config.ajaxUrl, {
        method: "POST",
        body,
        credentials: "same-origin",
        signal: request.signal,
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error("preview");
      if (current !== version) return;
      const doc = document.implementation.createHTMLDocument(
        t("preview", "Gallery preview"),
      );
      const meta = doc.createElement("meta");
      meta.name = "viewport";
      meta.content = "width=device-width,initial-scale=1";
      doc.head.append(meta);
      const style = doc.createElement("style");
      style.textContent =
        "body{margin:0;padding:12px;font-family:system-ui;background:#fff}";
      doc.head.append(style);
      result.data.css.forEach((url) => {
        const link = doc.createElement("link");
        link.rel = "stylesheet";
        link.href = url;
        doc.head.append(link);
      });
      doc.body.innerHTML = result.data.html;
      result.data.js.forEach((url) => {
        const script = doc.createElement("script");
        script.src = url;
        doc.body.append(script);
      });
      const frame = document.createElement("iframe");
      frame.title = t("preview", "Gallery preview");
      frame.setAttribute("sandbox", "allow-scripts");
      frame.srcdoc = "<!doctype html>" + doc.documentElement.outerHTML;
      preview.replaceChildren(frame);
    } catch (error) {
      if (error.name !== "AbortError" && current === version)
        preview.textContent = t(
          "previewError",
          "Preview could not load. Change a setting to retry.",
        );
    } finally {
      if (current === version) preview.removeAttribute("aria-busy");
    }
  }
  function schedule() {
    controls();
    clearTimeout(timer);
    request?.abort();
    version++;
    timer = setTimeout(refreshPreview, 300);
  }
  function decorate(item) {
    if (item.querySelector(".igs-order")) return;
    const order = document.createElement("div");
    order.className = "igs-order";
    [
      ["earlier", "←"],
      ["later", "→"],
    ].forEach(([direction, text]) => {
      const button = document.createElement("button");
      button.type = "button";
      button.textContent = text;
      button.dataset.direction = direction;
      button.setAttribute("aria-label", t(direction, direction));
      order.append(button);
    });
    item.append(order);
    item
      .querySelector(".igs-remove")
      ?.setAttribute("aria-label", t("remove", "Remove image"));
  }
  if (grid) {
    grid.querySelectorAll(".igs-media-item").forEach(decorate);
    let dragged;
    grid.addEventListener("dragstart", (e) => {
      dragged = e.target.closest(".igs-media-item");
      if (dragged) {
        e.dataTransfer.setData("text/plain", dragged.dataset.id);
        e.dataTransfer.effectAllowed = "move";
        dragged.classList.add("is-dragging");
      }
    });
    grid.addEventListener("dragover", (e) => {
      if (!dragged) return;
      e.preventDefault();
      const target = e.target.closest(".igs-media-item");
      if (target && target !== dragged) {
        const rect = target.getBoundingClientRect();
        grid.insertBefore(
          dragged,
          e.clientX < rect.left + rect.width / 2 ? target : target.nextSibling,
        );
      }
    });
    grid.addEventListener("drop", (e) => e.preventDefault());
    grid.addEventListener("dragend", () => {
      dragged?.classList.remove("is-dragging");
      dragged = null;
      schedule();
    });
    grid.addEventListener("click", (e) => {
      const button = e.target.closest("button"),
        item = button?.closest(".igs-media-item");
      if (!item) return;
      if (button.classList.contains("igs-remove")) {
        const next = item.nextElementSibling || item.previousElementSibling;
        item.remove();
        (
          next?.querySelector("button") ||
          document.getElementById("igs-add-media")
        ).focus();
      } else if (
        button.dataset.direction === "earlier" &&
        item.previousElementSibling
      )
        grid.insertBefore(item, item.previousElementSibling);
      else if (button.dataset.direction === "later" && item.nextElementSibling)
        grid.insertBefore(item.nextElementSibling, item);
      button.isConnected && button.focus();
      schedule();
    });
  }
  $("#igs-add-media").on("click", () => {
    const frame = wp.media({
      title: t("selectImages", "Select gallery images"),
      button: { text: t("addToGallery", "Add to gallery") },
      multiple: true,
      library: { type: "image" },
    });
    frame.on("select", () => {
      const existing = new Set(
        [...grid.querySelectorAll(".igs-media-item")].map(
          (item) => item.dataset.id,
        ),
      );
      frame
        .state()
        .get("selection")
        .each((model) => {
          const attachment = model.toJSON(),
            id = String(attachment.id);
          if (existing.has(id)) return;
          existing.add(id);
          const item = document.createElement("div");
          item.className = "igs-media-item";
          item.draggable = true;
          item.dataset.id = id;
          const img = new Image();
          img.src = attachment.sizes?.thumbnail?.url || attachment.url;
          img.alt = attachment.alt || attachment.title || "";
          img.draggable = false;
          const remove = document.createElement("button");
          remove.type = "button";
          remove.className = "igs-remove";
          remove.textContent = "×";
          const input = document.createElement("input");
          input.type = "hidden";
          input.name = "igs_media_ids[]";
          input.value = id;
          item.append(img, remove, input);
          decorate(item);
          grid.append(item);
        });
      schedule();
    });
    frame.open();
  });
  builder?.addEventListener("input", (e) => {
    const input = e.target;
    if (input.type === "range")
      input.closest(".igs-control").querySelector("output").textContent =
        `${input.value}${input.dataset.suffix || ""}`;
    schedule();
  });
  const barcode = document.getElementById("igs-barcode-preview");
  if (barcode) {
    const img = new Image();
    img.alt = t("barcode", "Gallery barcode");
    const url = new URL(config.ajaxUrl);
    url.search = new URLSearchParams({
      action: "igs_barcode",
      nonce: config.barcodeNonce,
      url: barcode.dataset.url,
    }).toString();
    img.src = url.href;
    barcode.append(img);
    document
      .getElementById("igs-print-barcode")
      ?.addEventListener("click", () => {
        const popup = window.open("", "_blank", "width=1000,height=500");
        if (!popup) {
          window.alert(t("popupError", "Allow popups to print the barcode."));
          return;
        }
        const printImage = popup.document.createElement("img");
        printImage.alt = img.alt;
        printImage.addEventListener(
          "load",
          () => {
            popup.focus();
            popup.print();
          },
          { once: true },
        );
        printImage.src = img.src;
        popup.document.title = img.alt;
        popup.document.body.append(printImage);
      });
  }
  refreshPreview();
})(jQuery);
