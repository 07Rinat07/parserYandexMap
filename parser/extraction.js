export const reviewSelectors = [
  '[class*="business-review-view"]',
  '[class*="business-reviews-card-view__review"]',
  '[class*="review-snippet-view"]',
  '[data-review-id]',
  '[data-testid*="review"]'
];

export const parserContractVersion = '2026-06-13.dom-v2';

export const fieldSelectorProfiles = {
  title: [
    'h1',
    '[class*="orgpage-header-view__header"]',
    '[class*="business-card-title-view__title"]'
  ],
  rating: [
    '[class*="business-rating-badge-view__rating"]',
    '[class*="business-summary-rating-badge-view__rating"]',
    '[class*="orgpage-header-view__rating"]',
    '[aria-label*="рейтинг"]',
    '[aria-label*="Рейтинг"]'
  ],
  author: [
    '[class*="business-review-view__author-name"]',
    '[class*="business-review-view__author"]',
    '[class*="review-snippet-view__name"]',
    '[class*="author"]'
  ],
  reviewDate: [
    'time',
    '[datetime]',
    '[class*="business-review-view__date"]',
    '[class*="review-snippet-view__date"]',
    '[class*="date"]'
  ],
  reviewText: [
    '[class*="business-review-view__body"]',
    '[class*="business-review-view__text"]',
    '[class*="review-snippet-view__text"]',
    '[class*="spoiler-view__text"]',
    '[class*="text"]'
  ]
};

export const expansionLabels = [
  'Читать полностью',
  'Показать полностью',
  'ещё',
  'Еще'
];

export const moreReviewsLabels = [
  /Показать ещё/i,
  /Загрузить ещё/i,
  /Ещё отзывы/i
];
