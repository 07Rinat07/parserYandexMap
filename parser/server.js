import { spawn } from 'node:child_process';
import http from 'node:http';

const port = Number(process.env.PARSER_PORT || 3000);

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

function runParser(url, maxReviews, timeout) {
  return new Promise((resolve) => {
    const child = spawn('node', ['yandex-parser.js', url], {
      cwd: new URL('.', import.meta.url),
      env: {
        ...process.env,
        YANDEX_MAX_REVIEWS: String(maxReviews || process.env.YANDEX_MAX_REVIEWS || 700),
        YANDEX_PARSER_TIMEOUT: String(timeout || process.env.YANDEX_PARSER_TIMEOUT || 180)
      },
      stdio: ['ignore', 'pipe', 'pipe']
    });

    let stdout = '';
    let stderr = '';
    child.stdout.on('data', (chunk) => {
      stdout += chunk.toString();
    });
    child.stderr.on('data', (chunk) => {
      stderr += chunk.toString();
    });
    child.on('close', (code) => {
      if (code !== 0) {
        resolve({
          status: 500,
          payload: { error: { code: 'PARSER_PROCESS_FAILED', message: stderr || 'Parser process failed.' } }
        });
        return;
      }

      try {
        resolve({ status: 200, payload: JSON.parse(stdout) });
      } catch {
        resolve({
          status: 500,
          payload: { error: { code: 'INVALID_PARSER_JSON', message: 'Parser process returned invalid JSON.' } }
        });
      }
    });
  });
}

const server = http.createServer(async (request, response) => {
  if (request.method === 'GET' && request.url === '/health') {
    send(response, 200, { status: 'ok' });
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

    const result = await runParser(payload.url, payload.max_reviews, payload.timeout);
    send(response, result.status, result.payload);
  } catch (error) {
    send(response, 400, { error: { code: 'BAD_REQUEST', message: error.message } });
  }
});

server.listen(port, '0.0.0.0', () => {
  console.error(`Parser microservice listening on ${port}`);
});
