import { expansionLabels, fieldSelectorProfiles, moreReviewsLabels, parserContractVersion, reviewSelectors } from './extraction.js';

export function withReviewsTab(rawUrl) {
  const parsed = new URL(rawUrl);
  parsed.searchParams.set('tab', 'reviews');
  return parsed.toString();
}

export function normalizeReviewDate(label, now = new Date()) {
  if (!label) return null;

  const value = label.replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
  const base = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
  const shiftDays = (days) => {
    const date = new Date(base);
    date.setUTCDate(date.getUTCDate() - days);
    return date.toISOString().slice(0, 10);
  };

  if (/сегодня/.test(value)) return shiftDays(0);
  if (/вчера/.test(value)) return shiftDays(1);

  const days = value.match(/(\d+)\s+(?:день|дня|дней)\s+назад/);
  if (days) return shiftDays(Number(days[1]));

  const weeks = value.match(/(\d+)\s+(?:неделю|недели|недель)\s+назад/);
  if (weeks) return shiftDays(Number(weeks[1]) * 7);

  const months = value.match(/(\d+)\s+(?:месяц|месяца|месяцев)\s+назад/);
  if (months) return shiftDays(Number(months[1]) * 30);

  const years = value.match(/(\d+)\s+(?:год|года|лет)\s+назад/);
  if (years) return shiftDays(Number(years[1]) * 365);

  const ruDate = value.match(/(\d{1,2})\s+(января|февраля|марта|апреля|мая|июня|июля|августа|сентября|октября|ноября|декабря)(?:\s+(\d{4}))?/);
  if (ruDate) {
    const monthsMap = {
      января: 0,
      февраля: 1,
      марта: 2,
      апреля: 3,
      мая: 4,
      июня: 5,
      июля: 6,
      августа: 7,
      сентября: 8,
      октября: 9,
      ноября: 10,
      декабря: 11
    };
    const year = ruDate[3] ? Number(ruDate[3]) : now.getUTCFullYear();
    const date = new Date(Date.UTC(year, monthsMap[ruDate[2]], Number(ruDate[1])));
    return date.toISOString().slice(0, 10);
  }

  const iso = value.match(/\d{4}-\d{2}-\d{2}/);
  return iso?.[0] || null;
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
  await page.evaluate((labels) => {
    const buttons = Array.from(document.querySelectorAll('button, [role="button"], a'));

    for (const button of buttons) {
      const text = button.textContent?.trim() || '';
      if (labels.some((label) => text.includes(label))) {
        button.click();
      }
    }
  }, expansionLabels).catch(() => {});
}

async function scrollReviews(page, startedAt, timeoutMs, maxReviews) {
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
            score: reviewCount * 100000 + node.scrollHeight + rect.height
          };
        })
        .sort((a, b) => b.score - a.score);

      const target = candidates[0]?.node || document.scrollingElement || document.documentElement;
      target.scrollTop = target.scrollHeight;
      window.scrollBy(0, Math.floor(window.innerHeight * 0.85));

      return {
        count: reviews.length,
        reachedLimit: reviews.length >= maxReviews
      };
    }, { selector, maxReviews });

    await clickIfVisible(page, moreReviewsLabels, 700);
    await page.waitForTimeout(850);

    if (state.reachedLimit) break;
    stableRounds = state.count === previousCount ? stableRounds + 1 : 0;
    previousCount = state.count;
    if (stableRounds >= 7) break;
  }
}

export async function parseYandexOrganization({ browser, url, maxReviews = 700, timeoutSeconds = 180 }) {
  const timeoutMs = Number(timeoutSeconds) * 1000;
  const startedAt = Date.now();
  const context = await browser.newContext({
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
    viewport: { width: 1365, height: 900 },
    userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36'
  });
  const page = await context.newPage();

  try {
    page.setDefaultTimeout(Math.min(timeoutMs, 45000));
    await page.goto(withReviewsTab(url), {
      waitUntil: 'domcontentloaded',
      timeout: Math.min(timeoutMs, 60000)
    });
    await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(2500);

    await clickIfVisible(page, [/Принять/i, /Хорошо/i, /Понятно/i], 2000);

    const bodyText = await page.locator('body').innerText({ timeout: 10000 }).catch(() => '');
    if (/captcha|подтвердите|робот|access denied|доступ ограничен/i.test(bodyText)) {
      return { error: { code: 'YANDEX_BLOCKED', message: 'Yandex Maps requested verification or blocked the parser.' } };
    }

    await clickIfVisible(page, [/Отзывы/i], 7000);
    await page.waitForTimeout(1500);
    await scrollReviews(page, startedAt, timeoutMs, maxReviews);

    const data = await page.evaluate(({ fieldSelectorProfiles, parserContractVersion, reviewSelectors, maxReviews }) => {
      const selectorHits = {};

      function textOf(root, selectors) {
        for (const selector of selectors) {
          const element = root.querySelector(selector);
          const value = element?.textContent?.replace(/\s+/g, ' ').trim();
          if (value) {
            selectorHits[selector] = (selectorHits[selector] || 0) + 1;
            return value;
          }
        }
        return null;
      }

      function parseHumanCount(value) {
        if (!value) return null;
        const normalized = value.replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
        const match = normalized.match(/([\d\s]+(?:[,.]\d+)?)\s*(тыс\.?|k)?/i);
        if (!match) return null;
        const number = Number(match[1].replace(/\s/g, '').replace(',', '.'));
        return Number.isFinite(number) ? Math.round(number * (match[2] ? 1000 : 1)) : null;
      }

      function parseRating(value) {
        if (!value) return null;
        const normalized = value.replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
        const decimal = normalized.match(/([1-5])\s*[,.]\s*(\d)/);
        if (decimal) {
          return Number(`${decimal[1]}.${decimal[2]}`);
        }

        const integer = normalized.match(/(?:^|[^\d])([1-5])(?:[^\d]|$)/);
        return integer ? Number(`${integer[1]}.0`) : null;
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

      const body = document.body.textContent?.replace(/\s+/g, ' ') || '';
      const reviewsMatch = body.match(/([\d\s]+(?:[,.]\d+)?\s*(?:тыс\.?|k)?)\s+отзыв/i);
      const ratingsMatch = body.match(/([\d\s]+(?:[,.]\d+)?\s*(?:тыс\.?|k)?)\s+оцен/i);
      const reviewNodes = Array.from(document.querySelectorAll(reviewSelectors.join(',')))
        .filter((node) => {
          const text = node.textContent?.trim() || '';
          return text.length > 20 && /зв|оцен|отзыв|читать|ещё|еще/i.test(text);
        });

      const title = textOf(document, fieldSelectorProfiles.title);
      const ratingText = textOf(document, fieldSelectorProfiles.rating);

      const reviews = [];
      const seenReviews = new Set();

      for (const [index, node] of reviewNodes.entries()) {
        const author = textOf(node, fieldSelectorProfiles.author);
        const date = textOf(node, fieldSelectorProfiles.reviewDate);
        const text = textOf(node, fieldSelectorProfiles.reviewText);
        const datetime = node.querySelector('time')?.getAttribute('datetime')
          || node.querySelector('[datetime]')?.getAttribute('datetime')
          || null;
        const externalId = node.getAttribute('data-review-id')
          || node.getAttribute('data-id')
          || node.id
          || `dom-${index}-${fingerprintText(author)}-${fingerprintText(date)}-${fingerprintText(text)}`;

        const review = {
          external_id: externalId,
          author_name: author,
          review_date: datetime ? datetime.slice(0, 10) : null,
          review_date_label: date,
          text,
          rating: extractReviewRating(node),
          raw_payload: { date_label: date }
        };

        const identity = [fingerprintText(author), fingerprintText(date), fingerprintText(text)].join('|');
        if ((review.author_name || review.review_date_label || review.rating) && review.text && !seenReviews.has(identity)) {
          seenReviews.add(identity);
          reviews.push(review);
        }

        if (reviews.length >= maxReviews) {
          break;
        }
      }

      const warnings = [];
      if (!title) warnings.push('missing_title');
      if (!parseRating(ratingText)) warnings.push('missing_rating');
      if (!parseHumanCount(reviewsMatch?.[1] || null)) warnings.push('missing_reviews_count');
      if (!parseHumanCount(ratingsMatch?.[1] || null)) warnings.push('missing_ratings_count');
      if (reviews.length === 0) warnings.push('missing_reviews');
      if (reviews.length > 0 && reviews.filter((review) => review.rating).length / reviews.length < 0.5) warnings.push('low_review_rating_coverage');
      if (reviews.length > 0 && reviews.filter((review) => review.text).length / reviews.length < 0.5) warnings.push('low_review_text_coverage');

      let confidence = 100;
      confidence -= warnings.length * 12;
      if (reviews.length > 0) confidence += 10;
      confidence = Math.max(0, Math.min(100, confidence));

      return {
        name: title,
        rating: parseRating(ratingText),
        ratings_count: parseHumanCount(ratingsMatch?.[1] || null),
        reviews_count: parseHumanCount(reviewsMatch?.[1] || null) || reviews.length,
        reviews,
        parser: {
          contract_version: parserContractVersion,
          strategy: 'dom_headless_scroll',
          confidence,
          warnings,
          diagnostics: {
            review_nodes_seen: reviewNodes.length,
            selector_hits: selectorHits,
            body_text_length: body.length
          }
        }
      };
    }, { fieldSelectorProfiles, parserContractVersion, reviewSelectors, maxReviews });

    const normalizedReviews = data.reviews.map((review) => ({
      ...review,
      review_date: review.review_date || normalizeReviewDate(review.review_date_label),
      raw_payload: {
        ...(review.raw_payload || {}),
        normalized_date_from_label: review.review_date ? null : normalizeReviewDate(review.review_date_label)
      }
    }));
    const result = {
      ...data,
      reviews: normalizedReviews,
      parser: {
        ...(data.parser || {}),
        diagnostics: {
          ...(data.parser?.diagnostics || {}),
          normalized_dates: normalizedReviews.filter((review) => review.raw_payload?.normalized_date_from_label).length,
          elapsed_ms: Date.now() - startedAt
        }
      }
    };

    if (!result.reviews.length && !result.rating && !result.reviews_count) {
      return { error: { code: 'YANDEX_PARSING_FAILED', message: 'Unable to extract organization reviews from Yandex Maps.' } };
    }

    return result;
  } finally {
    await context.close().catch(() => {});
  }
}
