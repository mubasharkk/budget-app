import { formatCurrency, getDefaultCurrency, localeForCurrency } from '@/utils/money';

const dayFormat = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short' });
const dayYearFormat = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
const toDate = (value) => new Date(`${String(value).slice(0, 10)}T00:00:00`);

export const formatDay = (value, withYear = false) => (withYear ? dayYearFormat : dayFormat).format(toDate(value));

export function formatRange(start, end) {
    const sameYear = start.slice(0, 4) === end.slice(0, 4);

    return `${(sameYear ? dayFormat : dayYearFormat).format(toDate(start))} – ${dayYearFormat.format(toDate(end))}`;
}

export const formatSigned = (amount) => `${amount > 0 ? '+' : amount < 0 ? '−' : ''}${formatCurrency(Math.abs(amount))}`;

export const formatPercent = (value) => `${Math.abs(value).toLocaleString(localeForCurrency(getDefaultCurrency()), { maximumFractionDigits: 1 })}%`;

export const unitName = (unit) => (unit === 'pcs' ? 'piece' : unit);

/** For consumption, spending less is the good direction. */
export const changeTone = (amount) => (amount > 0 ? 'text-red-700' : amount < 0 ? 'text-green-700' : 'text-gray-600');
