import { router } from '@inertiajs/react';

const OFFER_PROPS = ['tab', 'filters', 'syncedAt', 'availableRetailers', 'offers', 'forYou', 'comparisons'];
const PRICE_PROPS = ['tab', 'priceRange', 'prices'];

/**
 * Query values worth putting in the URL: drops empty strings, nulls and false.
 */
const toQuery = (params) =>
    Object.fromEntries(
        Object.entries(params)
            .filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== false)
            .map(([key, value]) => [key, value === true ? 1 : value]),
    );

/**
 * Re-requests the Deals page with new URL state, reloading only the props that
 * state affects. Filters live in the URL, so refresh, back and shared links keep them.
 */
function visit(params, only, options = {}) {
    router.get(route('deals'), toQuery(params), {
        only,
        preserveState: true,
        preserveScroll: true,
        replace: true,
        ...options,
    });
}

export const showOffers = (filters, options) => visit(filters, OFFER_PROPS, options);

export const showPrices = (range, options) => visit({ tab: 'prices', ...range }, PRICE_PROPS, options);

export const OFFER_POLL_PROPS = OFFER_PROPS.filter((prop) => prop !== 'tab' && prop !== 'filters');
