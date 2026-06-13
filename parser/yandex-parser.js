import { chromium } from 'playwright';

const url = process.argv[2];
const maxReviews = Number(process.env.YANDEX_MAX_REVIEWS || 700);
const timeoutMs = Number(process.env.YANDEX_PARSER_TIMEOUT || 180) * 1000;

function printError(code, message) {
  process.stdout.write(JSON.stringify({ error: { code, message } }));
}

function textOf(root, selectors) {
  for (const selector of selectors) {
    const element = root.querySelector(selector);
    const value = element?.textContent?.trim();
    if (value) return value;
  }
  return null;
}

function parseNumber(value) {
  if (!value) return null;
  const normalized = value.replace(/\s+/g, '').replace(',', '.');
  const match = normalized.match(/[\d.]+/);
  return match ? Number(match[0]) : null;
}

function parseCount(value) {
  if (!value) return null;
  const normalized = value.replace(/\s+/g, '').replace(/[^\d]/g, '');
  return normalized ? Number(normalized) : null;
}

async function main() {
  if (!url) {
    printError('INVALID_ARGUMENT', 'Yandex Maps URL is required.');
    return;
  }

  let browser;
  const startedAt = Date.now();

  try {
    browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({
      locale: 'ru-RU',
      viewport: { width: 1365, height: 900 }
    });
    page.setDefaultTimeout(Math.min(timeoutMs, 45000));

    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: Math.min(timeoutMs, 60000) });
    await page.waitForTimeout(2500);

    const bodyText = await page.locator('body').innerText({ timeout: 10000 }).catch(() => '');
    if (/captcha|подтвердите|робот|access denied/i.test(bodyText)) {
      printError('YANDEX_BLOCKED', 'Yandex Maps requested verification or blocked the parser.');
      return;
    }

    await page.getByText(/Отзывы/i).first().click({ timeout: 7000 }).catch(() => {});
    await page.waitForTimeout(1500);

    const selectors = [
      '[class*="business-reviews-card-view__review"]',
      '[class*="review-snippet-view"]',
      '[data-testid*="review"]'
    ];

    let previousCount = -1;
    for (let i = 0; i < 80 && Date.now() - startedAt < timeoutMs - 5000; i += 1) {
      const count = await page.locator(selectors.join(',')).count().catch(() => 0);
      if (count >= maxReviews || count === previousCount) {
        break;
      }
      previousCount = count;
      await page.mouse.wheel(0, 2400);
      await page.waitForTimeout(900);
    }

    const data = await page.evaluate(({ selectors, maxReviews }) => {
      function textOf(root, selectors) {
        for (const selector of selectors) {
          const element = root.querySelector(selector);
          const value = element?.textContent?.trim();
          if (value) return value;
        }
        return null;
      }

      function parseNumber(value) {
        if (!value) return null;
        const normalized = value.replace(/\s+/g, '').replace(',', '.');
        const match = normalized.match(/[\d.]+/);
        return match ? Number(match[0]) : null;
      }

      function parseCount(value) {
        if (!value) return null;
        const normalized = value.replace(/\s+/g, '').replace(/[^\d]/g, '');
        return normalized ? Number(normalized) : null;
      }

      const reviewNodes = Array.from(document.querySelectorAll(selectors.join(','))).slice(0, maxReviews);
      const title = document.querySelector('h1')?.textContent?.trim() || null;
      const ratingText = textOf(document, [
        '[class*="business-rating-badge-view__rating"]',
        '[class*="business-summary-rating-badge-view__rating"]',
        '[class*="rating"]'
      ]);
      const countersText = document.body.textContent || '';
      const reviewsMatch = countersText.match(/(\d[\d\s]*)\s+отзыв/i);
      const ratingsMatch = countersText.match(/(\d[\d\s]*)\s+оцен/i);

      const reviews = reviewNodes.map((node, index) => {
        const author = textOf(node, [
          '[class*="business-review-view__author"]',
          '[class*="review-snippet-view__name"]',
          '[class*="author"]'
        ]);
        const date = textOf(node, [
          'time',
          '[class*="business-review-view__date"]',
          '[class*="date"]'
        ]);
        const text = textOf(node, [
          '[class*="business-review-view__body"]',
          '[class*="review-snippet-view__text"]',
          '[class*="text"]'
        ]);
        const ratingLabel = node.querySelector('[aria-label*="зв"]')?.getAttribute('aria-label') || '';
        const ratingMatch = ratingLabel.match(/[1-5]/);
        const externalId = node.getAttribute('data-review-id') || node.id || null;

        return {
          external_id: externalId || `dom-${index}-${author || ''}-${date || ''}`,
          author_name: author,
          review_date: null,
          text,
          rating: ratingMatch ? Number(ratingMatch[0]) : null,
          raw_payload: { date_label: date }
        };
      }).filter((review) => review.author_name || review.text);

      return {
        name: title,
        rating: parseNumber(ratingText),
        ratings_count: parseCount(ratingsMatch?.[1] || null),
        reviews_count: parseCount(reviewsMatch?.[1] || null) || reviews.length,
        reviews
      };
    }, { selectors, maxReviews });

    process.stdout.write(JSON.stringify(data));
  } catch (error) {
    console.error(error);
    printError('YANDEX_PARSING_FAILED', 'Unable to load reviews from Yandex Maps.');
  } finally {
    await browser?.close().catch(() => {});
  }
}

main();
