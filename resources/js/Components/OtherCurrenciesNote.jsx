/**
 * Amounts left out of totals because they are in another currency (there is
 * no conversion). `amounts` are display strings, e.g. "$45.00 spent".
 */
export default function OtherCurrenciesNote({ amounts, className = '' }) {
    if (amounts.length === 0) {
        return null;
    }

    return (
        <p className={`bg-amber-50 p-3 text-sm text-amber-900 sm:rounded-md ${className}`}>
            Not counted, because it is in another currency: <strong>{amounts.join(', ')}</strong>. If a receipt or
            contract has the wrong currency, correct it there.
        </p>
    );
}

/**
 * Display strings for ExpenseService::otherCurrencies() rows (plus income).
 */
export function otherCurrencyAmounts(rows, formatIn) {
    return (rows ?? []).flatMap((row) => [
        row.spent > 0 && `${formatIn(row.spent, row.currency)} spent`,
        row.contracts > 0 && `${formatIn(row.contracts, row.currency)} in contracts`,
        row.income > 0 && `${formatIn(row.income, row.currency)} income`,
    ]).filter(Boolean);
}
