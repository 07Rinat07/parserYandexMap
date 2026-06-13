import { randomUUID } from 'node:crypto';
import { createClient } from 'redis';

export class RedisQueue {
  constructor({
    url = 'redis://redis:6379',
    prefix = 'yandex-parser'
  } = {}) {
    this.url = url;
    this.prefix = prefix;
    this.client = createClient({ url });
    this.ready = this.client.connect();
  }

  key(name) {
    return `${this.prefix}:${name}`;
  }

  async enqueue(payload) {
    await this.ready;
    const job = {
      id: randomUUID(),
      status: 'queued',
      payload,
      result: null,
      error: null,
      attempts: 0,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };

    await this.client.hSet(this.key(`job:${job.id}`), { data: JSON.stringify(job) });
    await this.client.lPush(this.key('queue'), job.id);
    await this.client.sAdd(this.key('jobs'), job.id);

    return job;
  }

  async nextQueued() {
    await this.ready;
    const id = await this.client.rPop(this.key('queue'));
    if (!id) return null;

    return this.markRunning(id);
  }

  async get(id) {
    await this.ready;
    const raw = await this.client.hGet(this.key(`job:${id}`), 'data');
    return raw ? JSON.parse(raw) : null;
  }

  async save(job) {
    job.updated_at = new Date().toISOString();
    await this.client.hSet(this.key(`job:${job.id}`), { data: JSON.stringify(job) });
    await this.client.publish(this.key(`job:${job.id}:events`), JSON.stringify(job));
    return job;
  }

  async markRunning(id) {
    const job = await this.get(id);
    if (!job) return null;

    job.status = 'running';
    job.attempts += 1;
    return this.save(job);
  }

  async markDone(id, result) {
    const job = await this.get(id);
    if (!job) return null;

    job.status = 'done';
    job.result = result;
    job.error = null;
    return this.save(job);
  }

  async markFailed(id, error) {
    const job = await this.get(id);
    if (!job) return null;

    job.status = 'failed';
    job.error = error;
    return this.save(job);
  }

  async waitFor(id, timeoutMs) {
    const existing = await this.get(id);
    if (existing && ['done', 'failed'].includes(existing.status)) return existing;

    const subscriber = this.client.duplicate();
    await subscriber.connect();

    return new Promise((resolve) => {
      const timeout = setTimeout(async () => {
        await subscriber.unsubscribe(this.key(`job:${id}:events`)).catch(() => {});
        await subscriber.quit().catch(() => {});
        resolve(this.get(id));
      }, timeoutMs);

      subscriber.subscribe(this.key(`job:${id}:events`), async (message) => {
        const job = JSON.parse(message);
        if (!['done', 'failed'].includes(job.status)) return;

        clearTimeout(timeout);
        await subscriber.unsubscribe(this.key(`job:${id}:events`)).catch(() => {});
        await subscriber.quit().catch(() => {});
        resolve(job);
      });
    });
  }

  async counts() {
    await this.ready;
    const ids = await this.client.sMembers(this.key('jobs'));
    const counts = { queued: 0, running: 0, done: 0, failed: 0 };
    const queuedDepth = await this.client.lLen(this.key('queue'));
    counts.queued = queuedDepth;

    for (const id of ids.slice(-1000)) {
      const job = await this.get(id);
      if (!job || job.status === 'queued') continue;
      counts[job.status] = (counts[job.status] || 0) + 1;
    }

    return counts;
  }

  async close() {
    await this.client.quit().catch(() => {});
  }
}
