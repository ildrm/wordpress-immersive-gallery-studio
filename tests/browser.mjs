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
const base = process.env.IGS_TEST_URL || "http://127.0.0.1:8876";
const browser = await chromium.launch({ channel: "chrome", headless: true });
const context = await browser.newContext({
  viewport: { width: 1280, height: 1000 },
});
const page = await context.newPage();
const errors = [];
page.on("pageerror", (error) => errors.push(error.message));
let checks = 0;
const check = (condition, description) => {
  assert.ok(condition, description);
  checks++;
  console.log(`PASS: ${description}`);
};
try {
  for (const [template, id] of Object.entries(fixtures.templates)) {
    await page.goto(`${base}/?post_type=igs_gallery&p=${id}`);
    const g = page.locator(".igs-gallery").first();
    await g.waitFor();
    await page.waitForFunction(
      () =>
        document.querySelector(".igs-gallery")?.dataset.igsCoreReady === "1",
    );
    check(
      (await g.locator(".igs-item").count()) === 3,
      `${template}: all media render in WordPress`,
    );
    check(
      await g.evaluate((el) => getComputedStyle(el).isolation === "isolate"),
      `${template}: assets load in block theme`,
    );
    const search = g.locator(".igs-search");
    await search.fill("Beta");
    check(
      (await g.locator(".igs-item:not([hidden])").count()) === 1,
      `${template}: search filters media`,
    );
    check(
      (await g.locator(".igs-counter").textContent()) === "1 / 1",
      `${template}: search updates counter`,
    );
    await search.fill("no-such-image");
    check(
      (await g.locator(".igs-empty").isVisible()) &&
        (await g.locator(".igs-counter").textContent()) === "0 / 0",
      `${template}: empty search state`,
    );
    await search.fill("");
    const item = g.locator(".igs-item:not([inert])").first();
    await item.focus();
    await page.keyboard.press("Enter");
    const box = page.getByRole("dialog", { name: "Image viewer" });
    await box.waitFor({ state: "visible" });
    check(
      (await box.locator("img").getAttribute("alt")) === "Alpha coast",
      `${template}: keyboard opens correct image`,
    );
    await page.keyboard.press("Tab");
    check(
      await box.evaluate((el) => el.contains(document.activeElement)),
      `${template}: lightbox traps focus`,
    );
    await page.keyboard.press("Escape");
    check(
      await item.evaluate((el) => document.activeElement === el),
      `${template}: lightbox restores focus`,
    );
    if (["carousel", "cinematic", "book3d", "ring3d"].includes(template)) {
      await g.locator(".igs-next").click();
      check(
        (await g.locator(".igs-counter").textContent()) === "2 / 3",
        `${template}: next selects second image`,
      );
      await g.locator(".igs-prev").click();
      check(
        (await g.locator(".igs-counter").textContent()) === "1 / 3",
        `${template}: previous selects first image`,
      );
    }
    if (template === "book3d") {
      await g.locator(".igs-next").click();
      await g.locator(".igs-next").click();
      check(
        (await g.locator(".igs-next").isDisabled()) &&
          (await g.locator(".igs-counter").textContent()) === "3 / 3",
        "book: last page remains visible and next is disabled",
      );
    }
    await page.setViewportSize({ width: 375, height: 812 });
    await page.waitForTimeout(100);
    check(
      await g.evaluate(
        (el) => el.getBoundingClientRect().right <= innerWidth + 1,
      ),
      `${template}: gallery fits mobile viewport`,
    );
    if (["book3d", "ring3d"].includes(template)) {
      await page.emulateMedia({ reducedMotion: "reduce" });
      await page.waitForTimeout(50);
      check(
        (await g.locator(".igs-item:not([inert])").count()) === 3,
        `${template}: reduced motion exposes all images`,
      );
      await page.emulateMedia({ reducedMotion: "no-preference" });
    }
    await page.setViewportSize({ width: 1280, height: 1000 });
    await page.waitForTimeout(650);
    if (["ring3d", "book3d", "story"].includes(template))
      await g.screenshot({ path: `/private/tmp/igs-${template}.png` });
  }
  await page.goto(`${base}/?page_id=${fixtures.page}`);
  check(
    (await page.locator(".igs-gallery .igs-item").count()) === 3,
    "shortcode renders with late assets",
  );
  await page.goto(`${base}/?post_type=igs_album&p=${fixtures.album}`);
  check(
    (await page.locator(".igs-album-card").count()) === 2 &&
      (await page.locator(".igs-album-card img").count()) === 1,
    "album displays public cover and hides locked cover",
  );
  const lockedUrl = `${base}/?post_type=igs_gallery&p=${fixtures.locked}`;
  const lockedResponse = await page.goto(lockedUrl);
  check(
    (lockedResponse.headers()["cache-control"] || "").includes("no-store"),
    "protected response prohibits caching",
  );
  check(
    !(await page
      .locator("body")
      .textContent()
      .then((text) => text.includes("Private gallery description secret"))),
    "locked page hides description",
  );
  const form = page.locator(".igs-password form");
  await form.locator("input[type=password]").fill("wrong");
  await form.locator("button").click();
  check(
    await page
      .locator("[role=alert]")
      .textContent()
      .then((text) => text.includes("Incorrect")),
    "wrong password shows useful error",
  );
  await page.locator(".igs-password input[type=password]").fill("review-pass");
  await page.locator(".igs-password button").click();
  check(
    (await page.locator(".igs-gallery .igs-item").count()) === 3,
    "correct password unlocks gallery",
  );
  const cookie = (await context.cookies()).find(
    (cookie) => cookie.name === `igs_access_${fixtures.locked}`,
  );
  check(
    cookie?.httpOnly &&
      cookie.sameSite === "Lax" &&
      /^\d{10}\.[a-f0-9]{64}$/.test(cookie.value),
    "unlock issues expiring HttpOnly signed cookie",
  );
  await page.goto(`${base}/?post_type=igs_gallery&p=${fixtures.logged_in}`);
  check(
    (await page.locator(".igs-gallery .igs-item").count()) === 0,
    "members gallery denies anonymous visitor",
  );
  await page.goto(`${base}/wp-login.php`);
  await page.locator("#user_login").fill("reviewer");
  await page.locator("#user_pass").fill("igs-local-review-only");
  await page.locator("#wp-submit").click();
  await page.waitForURL(/wp-admin/);
  await page.goto(
    `${base}/wp-admin/post.php?post=${fixtures.templates.grid}&action=edit`,
  );
  if (!(await page.locator("#igs_builder").isVisible())) {
    const pane = page.getByRole("button", { name: "Open visual builder", exact: true });
    if (await pane.isVisible()) await pane.click();
  }
  await page.locator("#igs_builder").waitFor();
  const preview = page.frameLocator("#igs-preview iframe");
  await preview.locator(".igs-item").first().waitFor({ timeout: 20000 });
  check(
    (await preview.locator(".igs-item").count()) === 3,
    "builder uses live production renderer",
  );
  await page
    .locator(".igs-template-card")
    .filter({ hasText: "3D Photo Book" })
    .click();
  await preview.locator(".igs-template-book3d.igs-enhanced").waitFor();
  check(
    (await preview.locator(".igs-template-book3d").count()) === 1,
    "builder preview switches to actual book template",
  );
  await page
    .locator(".igs-media-item")
    .nth(1)
    .getByRole("button", { name: "Move image earlier" })
    .click();
  check(
    (await page.locator(".igs-media-item").first().getAttribute("data-id")) ===
      String(fixtures.media[1]),
    "builder supports keyboard-accessible ordering",
  );
  check(
    errors.length === 0,
    `no uncaught browser errors: ${errors.join("; ")}`,
  );
  console.log(`${checks} browser checks passed`);
} finally {
  await browser.close();
}
