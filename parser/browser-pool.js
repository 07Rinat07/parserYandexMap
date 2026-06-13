import { chromium } from 'playwright';

export class BrowserPool {
  constructor({
    size = 2,
    maxTasksPerBrowser = 50,
    maxErrorsPerBrowser = 5
  } = {}) {
    this.size = Math.max(1, Number(size || 2));
    this.maxTasksPerBrowser = Math.max(1, Number(maxTasksPerBrowser || 50));
    this.maxErrorsPerBrowser = Math.max(1, Number(maxErrorsPerBrowser || 5));
    this.available = [];
    this.waiting = [];
    this.started = false;
    this.nextId = 1;
    this.recycled = 0;
  }

  async start() {
    if (this.started) return;
    this.started = true;

    for (let index = 0; index < this.size; index += 1) {
      this.available.push(await this.createBrowser());
    }
  }

  async createBrowser() {
    return {
      id: this.nextId++,
      browser: await chromium.launch({ headless: true }),
      tasks: 0,
      errors: 0
    };
  }

  async acquire() {
    await this.start();

    const entry = this.available.shift();
    if (entry) return entry;

    return new Promise((resolve) => {
      this.waiting.push(resolve);
    });
  }

  async release(entry, { failed = false } = {}) {
    entry.tasks += 1;
    if (failed) entry.errors += 1;

    if (entry.tasks >= this.maxTasksPerBrowser || entry.errors >= this.maxErrorsPerBrowser) {
      await entry.browser.close().catch(() => {});
      entry = await this.createBrowser();
      this.recycled += 1;
    }

    const next = this.waiting.shift();
    if (next) {
      next(entry);
      return;
    }

    this.available.push(entry);
  }

  async close() {
    const entries = this.available.splice(0);
    await Promise.all(entries.map((entry) => entry.browser.close().catch(() => {})));
  }
}
