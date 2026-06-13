import { chromium } from 'playwright';

const inputUrl = process.argv[2];
const maxReviews = Number(process.env.YANDEX_MAX_REVIEWS || 700);
const timeoutMs = Number(process.env.YANDEX_PARSER_TIMEOUT || 180) * 1000;

const reviewSelectors = [
  '[class*="business-review-view"]',
  '[class*="business-reviews-card-view__review"]',
  '[class*="review-snippet-view"]',
  '[data-review-id]',
  '[data-testid*="review"]'
];

function printError(code, message) {
  process.stdout.write(JSON.stringify({ error: { code, message } }));
}

function withReviewsTab(rawUrl) {
  const parsed = new URL(rawUrl);
  parsed.searchParams.set('tab', 'reviews');
  return parsed.toString();
}

async function clickIfVisible(page, patterns, timeout = 2500) {
  for (const pattern of patterns) {
    const locator = page.getByText(pattern).first();
    if (await locator.isVisible({ timeout }).catch(() => false)) {
      await locator.click({ timeout }).catch(() => {});
      await page.waitForTimeout(600);
      return true;
    }
  }

  return false;
}

async function expandVisibleReviewTexts(page) {
  await page.evaluate(() => {
    const labels = ['Читать полностью', 'Показать полностью', 'ещё', 'Еще'];
    const buttons = Array.from(document.querySelectorAll('button, [role="button"], a'));

    for (const button of buttons) {
      const text = button.textContent?.trim() || '';
      if (labels.some((label) => text.includes(label))) {
        button.click();
      }
    }
  }).catch(() => {});
}

async function scrollReviews(page, startedAt) {
  let previousCount = -1;
  let stableRounds = 0;
  const selector = reviewSelectors.join(',');

  for (let round = 0; round < 180 && Date.now() - startedAt < timeoutMs - 5000; round += 1) {
    await expandVisibleReviewTexts(page);

    const state = await page.evaluate(({ selector, maxReviews }) => {
      const reviews = Array.from(document.querySelectorAll(selector));
      const candidates = Array.from(document.querySelectorAll('main, aside, section, div'))
        .filter((node) => node.scrollHeight > node.clientHeight + 400)
        .map((node) => {
          const rect = node.getBoundingClientRect();
          const reviewCount = node.querySelectorAll(selector).length;

          return {
            node,
            score: reviewCount * 100000 + node.scrollHeight + rect.height,
          };
        })
        .sort((a, b) => b.score - a.score);

      const target = candidates[0]?.node || document.scrollingElement || document.documentElement;
      target.scrollTop = target.scrollHeight;
      window.scrollBy(0, Math.floor(window.innerHeight * 0.85));

      return {
        count: reviews.length,
        reachedLimit: reviews.length >= maxReviews,
        scrollTop: target.scrollTop,
        scrollHeight: target.scrollHeight,
        clientHeight: target.clientHeight,
      };
    }, { selector, maxReviews });

    await clickIfVisible(page, [/Показать ещё/i, /Загрузить ещё/i, /Ещё отзывы/i], 700);
    await page.waitForTimeout(850);

    if (state.reachedLimit) {
      break;
    }

    if (state.count === previousCount) {
      stableRounds += 1;
    } else {
      stableRounds = 0;
      previousCount = state.count;
    }

    if (stableRounds >= 7) {
      break;
    }
  }
}

async function main() {
  if (!inputUrl) {
    printError('INVALID_ARGUMENT', 'Yandex Maps URL is required.');
    return;
  }

  let browser;
  const startedAt = Date.now();

  try {
    browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({
      locale: 'ru-RU',
      timezoneId: 'Europe/Moscow',
      viewport: { width: 1365, height: 900 },
      userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36'
    });

    page.setDefaultTimeout(Math.min(timeoutMs, 45000));

    await page.goto(withReviewsTab(inputUrl), {
      waitUntil: 'domcontentloaded',
      timeout: Math.min(timeoutMs, 60000)
    });
    await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(2500);

    await clickIfVisible(page, [/Принять/i, /Хорошо/i, /Понятно/i], 2000);

    const bodyText = await page.locator('body').innerText({ timeout: 10000 }).catch(() => '');
    if (/captcha|подтвердите|робот|access denied|доступ ограничен/i.test(bodyText)) {
      printError('YANDEX_BLOCKED', 'Yandex Maps requested verification or blocked the parser.');
      return;
    }

    await clickIfVisible(page, [/Отзывы/i], 7000);
    await page.waitForTimeout(1500);

    await scrollReviews(page, startedAt);

    const data = await page.evaluate(({ reviewSelectors, maxReviews }) => {
      function textOf(root, selectors) {
        for (const selector of selectors) {
          const element = root.querySelector(selector);
          const value = element?.textContent?.replace(/\s+/g, ' ').trim();
          if (value) return value;
        }

        return null;
      }

      function parseHumanCount(value) {
        if (!value) return null;

        const normalized = value
          .replace(/\u00a0/g, ' ')
          .replace(/\s+/g, ' ')
          .trim()
          .toLowerCase();
        const match = normalized.match(/([\d\s]+(?:[,.]\d+)?)\s*(тыс\.?|k)?/i);
        if (!match) return null;

        const number = Number(match[1].replace(/\s/g, '').replace(',', '.'));
        if (!Number.isFinite(number)) return null;

        return Math.round(number * (match[2] ? 1000 : 1));
      }

      function parseRating(value) {
        if (!value) return null;
        const match = value.replace(',', '.').match(/([1-5](?:\.\d+)?)/);
        return match ? Number(match[1]) : null;
      }

      function extractCounts() {
        const body = document.body.textContent?.replace(/\s+/g, ' ') || '';
        const reviewsMatch = body.match(/([\d\s]+(?:[,.]\d+)?\s*(?:тыс\.?|k)?)\s+отзыв/i);
        const ratingsMatch = body.match(/([\d\s]+(?:[,.]\d+)?\s*(?:тыс\.?|k)?)\s+оцен/i);

        return {
          reviewsCount: parseHumanCount(reviewsMatch?.[1] || null),
          ratingsCount: parseHumanCount(ratingsMatch?.[1] || null),
        };
      }

      function extractReviewRating(node) {
        const aria = Array.from(node.querySelectorAll('[aria-label]'))
          .map((element) => element.getAttribute('aria-label') || '')
          .find((label) => /[1-5].*(зв|оцен)|зв.*[1-5]/i.test(label));
        const fromAria = parseRating(aria);
        if (fromAria) return Math.round(fromAria);

        const widthStar = Array.from(node.querySelectorAll('[style*="width"]'))
          .map((element) => element.getAttribute('style') || '')
          .map((style) => style.match(/width:\s*(\d+(?:\.\d+)?)%/i)?.[1])
          .filter(Boolean)
          .map(Number)
          .sort((a, b) => b - a)[0];

        return widthStar ? Math.max(1, Math.min(5, Math.round(widthStar / 20))) : null;
      }

      function fingerprintText(value) {
        return value ? value.toLowerCase().replace(/\s+/g, ' ').trim().slice(0, 120) : '';
      }

      const reviewNodes = Array.from(document.querySelectorAll(reviewSelectors.join(',')))
        .filter((node) => {
          const text = node.textContent?.trim() || '';
          return text.length > 20 && /зв|оцен|отзыв|читать|ещё|еще/i.test(text);
        })
        .slice(0, maxReviews);
      const counts = extractCounts();

      const title = textOf(document, [
        'h1',
        '[class*="orgpage-header-view__header"]',
        '[class*="business-card-title-view__title"]'
      ]);
      const ratingText = textOf(document, [
        '[class*="business-rating-badge-view__rating"]',
        '[class*="business-summary-rating-badge-view__rating"]',
        '[class*="orgpage-header-view__rating"]',
        '[aria-label*="рейтинг"]',
        '[aria-label*="Рейтинг"]'
      ]);

      const reviews = reviewNodes.map((node, index) => {
        const author = textOf(node, [
          '[class*="business-review-view__author"]',
          '[class*="business-review-view__author-name"]',
          '[class*="review-snippet-view__name"]',
          '[class*="author"]'
        ]);
        const date = textOf(node, [
          'time',
          '[datetime]',
          '[class*="business-review-view__date"]',
          '[class*="review-snippet-view__date"]',
          '[class*="date"]'
        ]);
        const text = textOf(node, [
          '[class*="business-review-view__body"]',
          '[class*="business-review-view__text"]',
          '[class*="review-snippet-view__text"]',
          '[class*="spoiler-view__text"]',
          '[class*="text"]'
        ]);
        const datetime = node.querySelector('time')?.getAttribute('datetime')
          || node.querySelector('[datetime]')?.getAttribute('datetime')
          || null;
        const externalId = node.getAttribute('data-review-id')
          || node.getAttribute('data-id')
          || node.id
          || `dom-${index}-${fingerprintText(author)}-${fingerprintText(date)}-${fingerprintText(text)}`;

        return {
          external_id: externalId,
          author_name: author,
          review_date: datetime ? datetime.slice(0, 10) : null,
          text,
          rating: extractReviewRating(node),
          raw_payload: { date_label: date }
        };
      }).filter((review) => review.author_name || review.text);

      return {
        name: title,
        rating: parseRating(ratingText),
        ratings_count: counts.ratingsCount,
        reviews_count: counts.reviewsCount || reviews.length,
        reviews
      };
    }, { reviewSelectors, maxReviews });

    if (!data.reviews.length && !data.rating && !data.reviews_count) {
      printError('YANDEX_PARSING_FAILED', 'Unable to extract organization reviews from Yandex Maps.');
      return;
    }

    process.stdout.write(JSON.stringify(data));
  } catch (error) {
    console.error(error);
    printError('YANDEX_PARSING_FAILED', 'Unable to load reviews from Yandex Maps.');
  } finally {
    await browser?.close().catch(() => {});
  }
}

main();
