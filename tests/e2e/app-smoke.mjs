import { chromium } from '../../parser/node_modules/playwright/index.mjs';

const baseUrl = process.env.E2E_BASE_URL || 'http://localhost:8080';
const email = process.env.E2E_EMAIL || 'test@example.com';
const password = process.env.E2E_PASSWORD || 'password';
const yandexUrl = process.env.E2E_YANDEX_URL || 'https://yandex.kz/maps/org/moskvarium/1367420415/reviews/';
const timeoutMs = Number(process.env.E2E_TIMEOUT_MS || 240000);

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ locale: 'ru-RU', acceptDownloads: true });

try {
  page.setDefaultTimeout(30000);
  await page.goto(baseUrl, { waitUntil: 'domcontentloaded' });

  if (await page.getByRole('button', { name: /войти/i }).isVisible().catch(() => false)) {
    await page.getByLabel(/email/i).fill(email);
    await page.getByLabel(/пароль/i).fill(password);
    await page.getByRole('button', { name: /войти/i }).click();
  }

  await page.getByRole('heading', { name: /отзывы яндекс\.карт/i }).waitFor();
  await page.getByLabel(/ссылка на организацию/i).fill(yandexUrl);
  await page.getByRole('button', { name: /сохранить/i }).click();

  await page.waitForFunction(() => {
    const body = document.body.innerText;
    return /Организация/i.test(body) && /Рейтинг/i.test(body) && /Отзывы/i.test(body);
  }, null, { timeout: timeoutMs });

  await page.getByText(/экспорт данных/i).waitFor({ timeout: 10000 });

  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('button', { name: /xlsx/i }).click();
  const download = await downloadPromise;
  const suggested = download.suggestedFilename();

  if (!suggested.endsWith('.xlsx')) {
    throw new Error(`Expected xlsx download, got ${suggested}`);
  }

  console.log(`E2E smoke passed: ${suggested}`);
} finally {
  await browser.close();
}
