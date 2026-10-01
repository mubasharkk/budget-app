const DEFAULT_LOCALE = 'de-DE';
const FALLBACK_CURRENCY = 'EUR';

let defaultCurrency = FALLBACK_CURRENCY;

/**
 * Set the currency used when an amount has no currency of its own —
 * the signed-in user's default currency (kept in sync from app.jsx).
 *
 * @param {string|null|undefined} currency ISO 4217 code
 */
export function setDefaultCurrency(currency) {
    defaultCurrency = currency || FALLBACK_CURRENCY;
}

/**
 * @returns {string} ISO 4217 code of the user's default currency
 */
export function getDefaultCurrency() {
    return defaultCurrency;
}

/**
 * Format a monetary amount as a localized currency string, always in the
 * signed-in user's default currency. Records' own stored currency codes are
 * deliberately not used for display, so the whole UI shows one currency.
 *
 * Returns 'N/A' for null/undefined/empty amounts to match existing UI behavior.
 *
 * @param {number|string|null|undefined} amount
 * @param {string} locale BCP 47 locale
 * @returns {string}
 */
export function formatCurrency(amount, locale = DEFAULT_LOCALE) {
    return formatCurrencyIn(amount, defaultCurrency, locale);
}

/**
 * Format an amount in a specific currency instead of the user's default.
 *
 * Only for views that must show a record's own stored currency (the receipt
 * detail page, where the currency can be corrected). Everywhere else use
 * formatCurrency so the UI stays in the user's default currency.
 *
 * @param {number|string|null|undefined} amount
 * @param {string|null|undefined} currency ISO 4217 code; falls back to the default
 * @param {string} locale BCP 47 locale
 * @returns {string}
 */
export function formatCurrencyIn(amount, currency, locale = DEFAULT_LOCALE) {
    if (amount === null || amount === undefined || amount === '') {
        return 'N/A';
    }

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency || defaultCurrency,
    }).format(Number(amount));
}
