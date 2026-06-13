import http from 'node:http';
import { BrowserPool } from './browser-pool.js';
import { parseYandexOrganization } from './parse-core.js';
import { PersistentQueue } from './persistent-queue.js';

const port = Number(process.env.PARSER_PORT || 3000);
const concurrency = Number(process.env.PARSER_CONCURRENCY || 2);
const maxQueueSize = Number(process.env.PARSER_MAX_QUEUE_SIZE || 50);
const syncWaitMs = Number(process.env.PARSER_SYNC_WAIT_MS || 180000);
const queueStore = new PersistentQueue(process.env.PARSER_QUEUE_FILE);
const pool = new BrowserPool({
  size: Number(process.env.PARSER_BROWSER_POOL_SIZE || concurrency),
  maxTasksPerBrowser: Number(process.env.PARSER_BROWSER_MAX_TASKS || 50),
  maxErrorsPerBrowser: Number(process.env.PARSER_BROWSER_MAX_ERRORS || 5)
});

let active = 0;
let completed = 0;
let failed = 0;
let rejected = 0;
let totalDurationMs = 0;

async function exportOtelSpan({ job, durationMs, failed: jobFailed }) {
  const endpoint = process.env.OTEL_EXPORTER_OTLP_ENDPOINT;
  if (!endpoint) return;

  const nowNs = BigInt(Date.now()) * 1000000n;
  const startNs = nowNs - BigInt(Math.max(0, durationMs)) * 1000000n;
  const traceId = job.id.replace(/-/g, '').padEnd(32, '0').slice(0, 32);
  const spanId = job.id.replace(/-/g, '').slice(0, 16);
  const payload = {
    resourceSpans: [{
      resource: {
        attributes: [{
          key: 'service.name',
          value: { stringValue: process.env.OTEL_SERVICE_NAME || 'yandex-parser-service' }
        }]
      },
      scopeSpans: [{
        scope: { name: 'parser/server.js' },
        spans: [{
          traceId,
          spanId,
          name: 'parse_yandex_organization',
          kind: 2,
          startTimeUnixNano: startNs.toString(),
          endTimeUnixNano: nowNs.toString(),
          status: { code: jobFailed ? 2 : 1 },
          attributes: [
            { key: 'parser.job_id', value: { stringValue: job.id } },
            { key: 'parser.failed', value: { boolValue: jobFailed } },
            { key: 'parser.attempts', value: { intValue: String(job.attempts) } }
          ]
        }]
      }]
    }]
  };

  await fetch(`${endpoint.replace(/\/$/, '')}/v1/traces`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify(payload)
  }).catch(() => {});
}

function readJson(request) {
  return new Promise((resolve, reject) => {
    let body = '';
    request.on('data', (chunk) => {
      body += chunk;
      if (body.length > 1024 * 32) {
        reject(new Error('Request body is too large.'));
      }
    });
    request.on('end', () => {
      try {
        resolve(body ? JSON.parse(body) : {});
      } catch (error) {
        reject(error);
      }
    });
    request.on('error', reject);
  });
}

function send(response, status, payload, headers = {}) {
  response.writeHead(status, { 'content-type': 'application/json; charset=utf-8', ...headers });
  response.end(JSON.stringify(payload));
}

function sendText(response, status, body) {
  response.writeHead(status, { 'content-type': 'text/plain; version=0.0.4; charset=utf-8' });
  response.end(body);
}

function queueDepth() {
  return queueStore.counts().queued;
}

function drainQueue() {
  while (active < concurrency) {
    const job = queueStore.queued()[0];
    if (!job) return;
    runJob(job.id);
  }
}

function runJob(jobId) {
  const job = queueStore.markRunning(jobId);
  if (!job) return;
  active += 1;

  (async () => {
    const started = Date.now();
    const entry = await pool.acquire();
    let jobFailed = false;

    try {
      const payload = await parseYandexOrganization({
        browser: entry.browser,
        url: job.payload.url,
        maxReviews: job.payload.max_reviews || process.env.YANDEX_MAX_REVIEWS || 700,
        timeoutSeconds: job.payload.timeout || process.env.YANDEX_PARSER_TIMEOUT || 180
      });

      if (payload?.error) {
        jobFailed = true;
        failed += 1;
        queueStore.markFailed(job.id, payload.error);
      } else {
        completed += 1;
        queueStore.markDone(job.id, payload);
      }
    } catch (error) {
      jobFailed = true;
      failed += 1;
      queueStore.markFailed(job.id, { code: 'PARSER_FAILED', message: error.message || 'Parser failed.' });
    } finally {
      const durationMs = Date.now() - started;
      totalDurationMs += durationMs;
      await exportOtelSpan({ job, durationMs, failed: jobFailed });
      await pool.release(entry, { failed: jobFailed });
      active -= 1;
      drainQueue();
    }
  })();
}

function enqueueParse(payload) {
  if (queueDepth() >= maxQueueSize) {
    rejected += 1;
    return null;
  }

  const job = queueStore.enqueue(payload);
  drainQueue();
  return job;
}

function metricsBody() {
  const counts = queueStore.counts();
  const avgDuration = completed + failed > 0 ? totalDurationMs / (completed + failed) / 1000 : 0;

  return [
    '# HELP parser_jobs_completed_total Completed parser jobs.',
    '# TYPE parser_jobs_completed_total counter',
    `parser_jobs_completed_total ${completed}`,
    '# HELP parser_jobs_failed_total Failed parser jobs.',
    '# TYPE parser_jobs_failed_total counter',
    `parser_jobs_failed_total ${failed}`,
    '# HELP parser_jobs_rejected_total Rejected parser jobs.',
    '# TYPE parser_jobs_rejected_total counter',
    `parser_jobs_rejected_total ${rejected}`,
    '# HELP parser_jobs_active Active parser jobs.',
    '# TYPE parser_jobs_active gauge',
    `parser_jobs_active ${active}`,
    '# HELP parser_jobs_queued Queued parser jobs.',
    '# TYPE parser_jobs_queued gauge',
    `parser_jobs_queued ${counts.queued}`,
    '# HELP parser_jobs_persisted Persisted parser jobs by status.',
    '# TYPE parser_jobs_persisted gauge',
    ...Object.entries(counts).map(([status, count]) => `parser_jobs_persisted{status="${status}"} ${count}`),
    '# HELP parser_browser_recycled_total Recycled browser instances.',
    '# TYPE parser_browser_recycled_total counter',
    `parser_browser_recycled_total ${pool.recycled}`,
    '# HELP parser_job_duration_seconds_avg Average parser job duration.',
    '# TYPE parser_job_duration_seconds_avg gauge',
    `parser_job_duration_seconds_avg ${avgDuration.toFixed(3)}`
  ].join('\n');
}

async function handleParse(request, response, waitForResult) {
  const payload = await readJson(request);
  if (!payload.url || typeof payload.url !== 'string') {
    send(response, 422, { error: { code: 'VALIDATION_FAILED', message: 'url is required.' } });
    return;
  }

  const job = enqueueParse(payload);
  if (!job) {
    send(response, 429, { error: { code: 'PARSER_QUEUE_FULL', message: 'Parser queue is full.' } });
    return;
  }

  if (!waitForResult) {
    send(response, 202, { data: job }, { location: `/jobs/${job.id}` });
    return;
  }

  const result = await queueStore.waitFor(job.id, syncWaitMs);
  if (!result || !['done', 'failed'].includes(result.status)) {
    send(response, 202, { data: queueStore.get(job.id) }, { location: `/jobs/${job.id}` });
    return;
  }

  if (result.status === 'failed') {
    send(response, 500, { error: result.error });
    return;
  }

  send(response, 200, result.result);
}

const server = http.createServer(async (request, response) => {
  const parsedUrl = new URL(request.url, `http://${request.headers.host || 'localhost'}`);

  if (request.method === 'GET' && parsedUrl.pathname === '/health') {
    send(response, 200, {
      status: 'ok',
      active,
      queued: queueDepth(),
      completed,
      failed,
      rejected,
      concurrency,
      pool_size: pool.size,
      recycled_browsers: pool.recycled
    });
    return;
  }

  if (request.method === 'GET' && parsedUrl.pathname === '/metrics') {
    sendText(response, 200, metricsBody());
    return;
  }

  if (request.method === 'POST' && parsedUrl.pathname === '/parse') {
    try {
      await handleParse(request, response, true);
    } catch (error) {
      send(response, 400, { error: { code: 'BAD_REQUEST', message: error.message } });
    }
    return;
  }

  if (request.method === 'POST' && parsedUrl.pathname === '/jobs') {
    try {
      await handleParse(request, response, false);
    } catch (error) {
      send(response, 400, { error: { code: 'BAD_REQUEST', message: error.message } });
    }
    return;
  }

  const jobMatch = parsedUrl.pathname.match(/^\/jobs\/([^/]+)$/);
  if (request.method === 'GET' && jobMatch) {
    const job = queueStore.get(jobMatch[1]);
    send(response, job ? 200 : 404, job ? { data: job } : { error: { code: 'NOT_FOUND', message: 'Job not found.' } });
    return;
  }

  send(response, 404, { error: { code: 'NOT_FOUND', message: 'Endpoint not found.' } });
});

process.on('SIGTERM', async () => {
  await pool.close();
  process.exit(0);
});

drainQueue();

server.listen(port, '0.0.0.0', () => {
  console.error(`Parser microservice listening on ${port} with concurrency=${concurrency}`);
});
