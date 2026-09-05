import fs from "node:fs";
import os from "node:os";
import assert from "node:assert/strict";
import { execFile } from "node:child_process";
import { promisify } from "node:util";
const exec = promisify(execFile);
const fixtures = JSON.parse(
  fs.readFileSync(`${os.tmpdir()}/igs-review-fixtures.json`, "utf8"),
);
const id = String(fixtures.templates.grid);
const run = (...args) =>
  exec("php", [
    "-d",
    "error_reporting=22527",
    "tests/track-worker.php",
    ...args,
  ]);
const before = +(await run(id, "read")).stdout;
const results = await Promise.all(Array.from({ length: 20 }, () => run(id)));
assert.ok(results.every((result) => JSON.parse(result.stdout).success));
const after = +(await run(id, "read")).stdout;
assert.equal(after - before, 20, "concurrent analytics must not lose events");
for (const key of ["locked", "logged_in", "private"]) {
  const denied = JSON.parse((await run(String(fixtures[key]))).stdout);
  assert.equal(
    denied.success,
    false,
    `${key} gallery must reject anonymous analytics`,
  );
}
console.log(
  "PASS: 20 concurrent increments counted exactly; locked, logged-in and private galleries reject unauthorized tracking.",
);

const rateKey = "igs_test_" + Date.now();
const attempts = await Promise.all(
  Array.from({ length: 20 }, () => run(rateKey, "attempt")),
);
assert.equal(attempts.filter((result) => JSON.parse(result.stdout)).length, 8);
console.log(
  "PASS: exactly eight of twenty concurrent password attempts are admitted.",
);
