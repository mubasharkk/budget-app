const DEFAULT_LOCALE = 'de-DE';
const DEFAULT_CURRENCY = 'EUR';

/**
 * Format a monetary amount as a localized currency string.
 *
 * Returns 'N/A' for null/undefined/empty amounts to match existing UI behavior.
 *
 * @param {number|string|null|undefined} amount
 * @param {string} currency ISO 4217 code
 * @param {string} locale BCP 47 locale
 * @returns {string}
 */
export function formatCurrency(amount, currency = DEFAULT_CURRENCY, locale = DEFAULT_LOCALE) {
    if (amount === null || amount === undefined || amount === '') {
        return 'N/A';
    }

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency || DEFAULT_CURRENCY,
    }).format(Number(amount));
}
