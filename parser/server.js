import http from 'node:http';
import { BrowserPool } from './browser-pool.js';
import { parseYandexOrganization } from './parse-core.js';

const port = Number(process.env.PARSER_PORT || 3000);
const concurrency = Number(process.env.PARSER_CONCURRENCY || 2);
const maxQueueSize = Number(process.env.PARSER_MAX_QUEUE_SIZE || 50);
const pool = new BrowserPool(Number(process.env.PARSER_BROWSER_POOL_SIZE || concurrency));
const queue = [];
let active = 0;
let completed = 0;
let failed = 0;

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

function send(response, status, payload) {
  response.writeHead(status, { 'content-type': 'application/json; charset=utf-8' });
  response.end(JSON.stringify(payload));
}

function runQueuedJob(job) {
  active += 1;

  (async () => {
    const browser = await pool.acquire();
    try {
      const payload = await parseYandexOrganization({
        browser,
        url: job.url,
        maxReviews: job.maxReviews,
        timeoutSeconds: job.timeout
      });

      completed += 1;
      job.resolve({ status: 200, payload });
    } catch (error) {
      failed += 1;
      job.resolve({
        status: 500,
        payload: { error: { code: 'PARSER_FAILED', message: error.message || 'Parser failed.' } }
      });
    } finally {
      pool.release(browser);
      active -= 1;
      drainQueue();
    }
  })();
}

function drainQueue() {
  while (active < concurrency && queue.length > 0) {
    runQueuedJob(queue.shift());
  }
}

function enqueueParse(payload) {
  if (queue.length >= maxQueueSize) {
    return Promise.resolve({
      status: 429,
      payload: { error: { code: 'PARSER_QUEUE_FULL', message: 'Parser queue is full.' } }
    });
  }

  return new Promise((resolve) => {
    queue.push({
      url: payload.url,
      maxReviews: payload.max_reviews || process.env.YANDEX_MAX_REVIEWS || 700,
      timeout: payload.timeout || process.env.YANDEX_PARSER_TIMEOUT || 180,
      resolve
    });
    drainQueue();
  });
}

const server = http.createServer(async (request, response) => {
  if (request.method === 'GET' && request.url === '/health') {
    send(response, 200, {
      status: 'ok',
      active,
      queued: queue.length,
      completed,
      failed,
      concurrency,
      pool_size: pool.size
    });
    return;
  }

  if (request.method !== 'POST' || request.url !== '/parse') {
    send(response, 404, { error: { code: 'NOT_FOUND', message: 'Endpoint not found.' } });
    return;
  }

  try {
    const payload = await readJson(request);
    if (!payload.url || typeof payload.url !== 'string') {
      send(response, 422, { error: { code: 'VALIDATION_FAILED', message: 'url is required.' } });
      return;
    }

    const result = await enqueueParse(payload);
    send(response, result.status, result.payload);
  } catch (error) {
    send(response, 400, { error: { code: 'BAD_REQUEST', message: error.message } });
  }
});

process.on('SIGTERM', async () => {
  await pool.close();
  process.exit(0);
});

server.listen(port, '0.0.0.0', () => {
  console.error(`Parser microservice listening on ${port} with concurrency=${concurrency}`);
});
