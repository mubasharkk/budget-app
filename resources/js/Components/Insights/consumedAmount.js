const UNIT_LABELS = { l: 'l', kg: 'kg', pcs: 'pcs' };

const numberFormat = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 });

/**
 * "3.5 l", "1.23 kg", "12 pcs" — or null when the amount is unknown.
 */
export function formatAmount(item) {
    if (item.amount === null || item.amount === undefined) {
        return null;
    }

    return `${numberFormat.format(Number(item.amount))} ${UNIT_LABELS[item.unit] ?? item.unit}`;
}

/**
 * "3.5 l · bought 3×", plus a note when some purchases had no known size.
 */
export function describeConsumption(item) {
    const parts = [];
    const amount = formatAmount(item);

    if (amount) {
        parts.push(amount);
    }

    parts.push(`bought ${item.purchase_count}×`);

    if (amount && item.unmeasured_count > 0) {
        parts.push(`${item.unmeasured_count} without a known size`);
    }

    return parts.join(' · ');
}
