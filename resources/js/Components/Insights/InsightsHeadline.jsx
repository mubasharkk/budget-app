import { formatCurrency } from '@/utils/money';
import { changeTone, formatDay, formatPercent, formatSigned } from './insightFormat';

function Tile({ label, value, detail, tone = 'text-gray-900' }) {
    return (
        <div className="min-w-0 bg-white p-4 shadow-sm sm:rounded-lg sm:p-5">
            <div className="text-sm font-medium text-gray-600">{label}</div>
            <div className={`mt-1 whitespace-nowrap text-xl font-semibold sm:text-2xl ${tone}`}>{value}</div>
            <div className="mt-0.5 text-xs text-gray-600">{detail}</div>
        </div>
    );
}

/**
 * The four figures that answer "how am I doing?".
 */
export default function InsightsHeadline({ totals }) {
    const { change, change_percent: percent, forecast, price_change_percent: price } = totals;
    const arrow = (value) => (value > 0 ? '▲ ' : value < 0 ? '▼ ' : '');

    return (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <Tile
                label="Spent on consumption"
                value={formatCurrency(totals.spend)}
                detail={`${totals.purchases} purchases of ${totals.products} products`}
            />
            <Tile
                label="Compared with before"
                value={change === null ? '—' : `${arrow(change)}${formatSigned(change)}`}
                tone={change === null ? 'text-gray-900' : changeTone(change)}
                detail={
                    change === null
                        ? 'Nothing recorded to compare with'
                        : `${percent === null ? '' : `${formatPercent(percent)} ${percent < 0 ? 'less' : 'more'} than `}${formatCurrency(totals.previous_spend)}`
                }
            />
            {forecast ? (
                <Tile
                    label={`Likely by ${formatDay(forecast.until)}`}
                    value={formatCurrency(forecast.total)}
                    detail="If the rest goes like last time"
                />
            ) : (
                <Tile label="Per day" value={formatCurrency(totals.per_day)} detail="On average in this period" />
            )}
            <Tile
                label="Your prices"
                value={price === null ? '—' : `${arrow(price)}${formatPercent(price)}`}
                tone={price === null ? 'text-gray-900' : changeTone(price)}
                detail={
                    price === null
                        ? 'Nothing comparable bought in both periods'
                        : price === 0
                          ? 'Unchanged per unit'
                          : `${price > 0 ? 'More' : 'Less'} paid per unit for the same things`
                }
            />
        </div>
    );
}
