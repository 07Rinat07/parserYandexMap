import fs from 'node:fs';
import path from 'node:path';
import { randomUUID } from 'node:crypto';

export class PersistentQueue {
  constructor(filePath) {
    this.filePath = filePath || path.resolve(process.cwd(), 'storage/parser-queue.json');
    this.jobs = new Map();
    this.waiters = new Map();
    fs.mkdirSync(path.dirname(this.filePath), { recursive: true });
    this.load();
  }

  load() {
    if (!fs.existsSync(this.filePath)) return;

    try {
      const rows = JSON.parse(fs.readFileSync(this.filePath, 'utf8'));
      for (const job of rows) {
        this.jobs.set(job.id, job.status === 'running' ? { ...job, status: 'queued' } : job);
      }
    } catch {
      this.jobs.clear();
    }
  }

  persist() {
    const rows = Array.from(this.jobs.values()).slice(-500);
    fs.writeFileSync(this.filePath, JSON.stringify(rows, null, 2));
  }

  enqueue(payload) {
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
    this.jobs.set(job.id, job);
    this.persist();
    return job;
  }

  queued() {
    return Array.from(this.jobs.values()).filter((job) => job.status === 'queued');
  }

  nextQueued() {
    const job = this.queued()[0];
    if (!job) return null;

    return this.markRunning(job.id);
  }

  get(id) {
    return this.jobs.get(id) || null;
  }

  markRunning(id) {
    const job = this.jobs.get(id);
    if (!job) return null;
    job.status = 'running';
    job.attempts += 1;
    job.updated_at = new Date().toISOString();
    this.persist();
    return job;
  }

  markDone(id, result) {
    const job = this.jobs.get(id);
    if (!job) return null;
    job.status = 'done';
    job.result = result;
    job.error = null;
    job.updated_at = new Date().toISOString();
    this.persist();
    this.resolveWaiter(id, job);
    return job;
  }

  markFailed(id, error) {
    const job = this.jobs.get(id);
    if (!job) return null;
    job.status = 'failed';
    job.error = error;
    job.updated_at = new Date().toISOString();
    this.persist();
    this.resolveWaiter(id, job);
    return job;
  }

  waitFor(id, timeoutMs) {
    const existing = this.jobs.get(id);
    if (existing && ['done', 'failed'].includes(existing.status)) {
      return Promise.resolve(existing);
    }

    return new Promise((resolve) => {
      const timeout = setTimeout(() => {
        this.waiters.delete(id);
        resolve(this.jobs.get(id) || null);
      }, timeoutMs);
      this.waiters.set(id, (job) => {
        clearTimeout(timeout);
        resolve(job);
      });
    });
  }

  resolveWaiter(id, job) {
    const waiter = this.waiters.get(id);
    if (!waiter) return;
    this.waiters.delete(id);
    waiter(job);
  }

  counts() {
    const counts = { queued: 0, running: 0, done: 0, failed: 0 };
    for (const job of this.jobs.values()) {
      counts[job.status] = (counts[job.status] || 0) + 1;
    }
    return counts;
  }
}
