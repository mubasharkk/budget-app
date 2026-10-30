import { formatCurrency } from '@/utils/money';

const DAY_MS = 24 * 60 * 60 * 1000;

const startOfDay = (date) => {
    const copy = new Date(date);
    copy.setHours(0, 0, 0, 0);

    return copy;
};

export const daysUntil = (isoDate) =>
    isoDate
        ? Math.round((startOfDay(isoDate) - startOfDay(new Date())) / DAY_MS)
        : null;

export const formatShortDate = (isoDate) =>
    new Date(isoDate).toLocaleDateString('de-DE', {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
    });

export const formatSyncTime = (isoDate) =>
    new Date(isoDate).toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });

export const formatUnitPrice = (offer) =>
    offer.unit_price
        ? `${formatCurrency(offer.unit_price)}/${offer.unit_price_unit}`
        : null;
