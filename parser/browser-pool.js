import { chromium } from 'playwright';

export class BrowserPool {
  constructor(size = 2) {
    this.size = Math.max(1, Number(size || 2));
    this.available = [];
    this.waiting = [];
    this.started = false;
  }

  async start() {
    if (this.started) return;
    this.started = true;

    for (let index = 0; index < this.size; index += 1) {
      this.available.push(await chromium.launch({ headless: true }));
    }
  }

  async acquire() {
    await this.start();

    const browser = this.available.shift();
    if (browser) return browser;

    return new Promise((resolve) => {
      this.waiting.push(resolve);
    });
  }

  release(browser) {
    const next = this.waiting.shift();
    if (next) {
      next(browser);
      return;
    }

    this.available.push(browser);
  }

  async close() {
    const browsers = this.available.splice(0);
    await Promise.all(browsers.map((browser) => browser.close().catch(() => {})));
  }
}
