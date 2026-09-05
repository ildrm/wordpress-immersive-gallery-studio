import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import { createRequire } from "node:module";
const require = createRequire(
  `${process.env.IGS_TEST_TOOLS || "/private/tmp/igs-review-tools"}/package.json`,
);
const { chromium } = require("playwright");
const fixtures = JSON.parse(
  fs.readFileSync(`${os.tmpdir()}/igs-review-fixtures.json`, "utf8"),
);
const base = process.env.IGS_TEST_URL || "http://127.0.0.1:8878";
const browser = await chromium.launch({ channel: "chrome", headless: true });
const page = await browser.newPage({ viewport: { width: 1100, height: 900 } });
const errors = [];
page.on("pageerror", (e) => errors.push(e.message));
const open = async (template) => {
  await page.goto(
    `${base}/?post_type=igs_gallery&p=${fixtures.templates[template]}`,
  );
  await page.locator(".igs-gallery").scrollIntoViewIfNeeded();
  return page.locator(".igs-gallery").first();
};
async function drag(g, dx) {
  const rect = await g.locator(".igs-stage").boundingBox();
  const x = rect.x + rect.width / 2,
    y = rect.y + rect.height / 2;
  await page.mouse.move(x, y);
  await page.mouse.down();
  await page.mouse.move(x + dx, y, { steps: 12 });
  await page.mouse.up();
  await page.waitForTimeout(500);
}
try {
  let g = await open("book3d");
  await g.locator(".igs-item:not([inert])").click();
  await page.getByRole("dialog").waitFor({ state: "visible" });
  await page.keyboard.press("Escape");
  console.log("PASS: pointer tap opens the book image");
  await drag(g, -200);
  assert.equal(await g.locator(".igs-counter").textContent(), "2 / 3");
  assert.equal(await page.getByRole("dialog").isVisible(), false);
  console.log("PASS: forward book drag advances without opening viewer");
  await drag(g, 200);
  assert.equal(await g.locator(".igs-counter").textContent(), "1 / 3");
  console.log("PASS: backward book drag returns to first page");
  g = await open("ring3d");
  await drag(g, 100);
  assert.equal(await page.getByRole("dialog").count(), 0);
  console.log("PASS: ring drag suppresses accidental viewer opening");
  for (const template of ["carousel", "cinematic"]) {
    g = await open(template);
    // Recreate a gallery with autoplay enabled to exercise timer lifecycle without persisting fixture settings.
    await g.evaluate((el) => {
      const replacement = el.cloneNode(true);
      const settings = JSON.parse(el.dataset.settings);
      settings.autoplay = 1;
      settings.autoplay_speed = 1500;
      settings.cinematic_duration = 2500;
      replacement.dataset.settings = JSON.stringify(settings);
      delete replacement.dataset.igsCoreReady;
      replacement.querySelectorAll(".igs-empty").forEach((x) => x.remove());
      el.replaceWith(replacement);
    });
    await g.locator(".igs-play").waitFor();
    await page.mouse.move(0, 0);
    await page.locator("body").click({ position: { x: 2, y: 2 } });
    const before = await g.locator(".igs-counter").textContent();
    await page.waitForTimeout(template === "carousel" ? 1700 : 2700);
    assert.notEqual(await g.locator(".igs-counter").textContent(), before);
    await g.locator(".igs-play").click();
    const paused = await g.locator(".igs-counter").textContent();
    await page.mouse.move(0, 0);
    await page.waitForTimeout(template === "carousel" ? 1700 : 2700);
    assert.equal(await g.locator(".igs-counter").textContent(), paused);
    console.log(`PASS: ${template} autoplay advances and pause holds position`);
  }
  g = await open("justified");
  const ratios = await g.locator(".igs-item").evaluateAll((items) =>
    items.map((item) => {
      const r = item.getBoundingClientRect();
      return Math.abs(r.width / r.height - +item.dataset.w / +item.dataset.h);
    }),
  );
  assert.ok(ratios.every((error) => error < 0.02));
  console.log("PASS: justified layout preserves source aspect ratios");
  await page.goto(`${base}/wp-login.php`);
  await page.locator("#user_login").fill("reviewer");
  await page.locator("#user_pass").fill("igs-local-review-only");
  await page.locator("#wp-submit").click();
  await page.goto(
    `${base}/wp-admin/post.php?post=${fixtures.templates.grid}&action=edit`,
  );
  if (!(await page.locator("#igs_builder").isVisible())) {
    const pane = page.getByRole("button", { name: "Open visual builder", exact: true });
    if (await pane.isVisible()) await pane.click();
  }
  await page.locator("#igs_builder").waitFor();
  // Dismiss the editor welcome modal, if WordPress shows it on this fresh profile.
  const close = page.getByRole("button", { name: "Close", exact: true });
  if (await close.isVisible()) await close.click();
  await page
    .locator(".igs-template-card")
    .filter({ hasText: "3D Photo Book" })
    .click();
  await page
    .locator(".igs-media-item")
    .nth(1)
    .getByRole("button", { name: "Move image earlier" })
    .click();
  const response = page.waitForResponse(
    (r) => r.url().includes("post.php") && r.request().method() === "POST",
  );
  const update = page.getByRole("button", { name: "Save", exact: true });
  if (await update.count()) await update.click();
  else await page.getByRole("button", { name: "Update", exact: true }).click();
  await response;
  await page.reload();
  assert.equal(
    await page
      .locator('[name="igs_settings[template]"][value="book3d"]')
      .isChecked(),
    true,
  );
  assert.equal(
    await page.locator(".igs-media-item").first().getAttribute("data-id"),
    String(fixtures.media[1]),
  );
  console.log(
    "PASS: actual editor save persists template and reordered image IDs",
  );
  const nonce = await page.evaluate(() => IGSAdmin.barcodeNonce);
  const svg = await page.request.get(
    `${base}/wp-admin/admin-ajax.php?action=igs_barcode&nonce=${nonce}&url=${encodeURIComponent(base + "/?p=123")}`,
  );
  assert.equal(svg.status(), 200);
  assert.ok((await svg.text()).includes("<svg"));
  const invalid = await page.request.get(
    `${base}/wp-admin/admin-ajax.php?action=igs_barcode&nonce=${nonce}`,
  );
  assert.equal(invalid.status(), 400);
  console.log(
    "PASS: authenticated barcode endpoint serves SVG and rejects missing URL",
  );
  assert.deepEqual(errors, []);
  console.log("PASS: no uncaught errors during gestures, autoplay or saving");
} finally {
  await browser.close();
}
