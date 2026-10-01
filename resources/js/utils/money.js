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
 * Format a monetary amount as a localized currency string.
 *
 * Returns 'N/A' for null/undefined/empty amounts to match existing UI behavior.
 * Falls back to the user's default currency when none is given.
 *
 * @param {number|string|null|undefined} amount
 * @param {string} [currency] ISO 4217 code
 * @param {string} locale BCP 47 locale
 * @returns {string}
 */
export function formatCurrency(amount, currency, locale = DEFAULT_LOCALE) {
    if (amount === null || amount === undefined || amount === '') {
        return 'N/A';
    }

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency || defaultCurrency,
    }).format(Number(amount));
}
