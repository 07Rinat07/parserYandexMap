import { chromium } from 'playwright';
import { parseYandexOrganization } from './parse-core.js';

const inputUrl = process.argv[2];
const maxReviews = Number(process.env.YANDEX_MAX_REVIEWS || 700);
const timeoutSeconds = Number(process.env.YANDEX_PARSER_TIMEOUT || 180);

function print(payload) {
  process.stdout.write(JSON.stringify(payload));
}

async function main() {
  if (!inputUrl) {
    print({ error: { code: 'INVALID_ARGUMENT', message: 'Yandex Maps URL is required.' } });
    return;
  }

  let browser;

  try {
    browser = await chromium.launch({ headless: true });
    print(await parseYandexOrganization({
      browser,
      url: inputUrl,
      maxReviews,
      timeoutSeconds
    }));
  } catch (error) {
    console.error(error);
    print({ error: { code: 'YANDEX_PARSING_FAILED', message: 'Unable to load reviews from Yandex Maps.' } });
  } finally {
    await browser?.close().catch(() => {});
  }
}

main();
