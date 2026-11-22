import { Link } from '@inertiajs/react';
import SecondaryButton from '@/Components/SecondaryButton';
import OfferTitle from '@/Components/Deals/OfferTitle';
import { formatCurrency } from '@/utils/money';
import { formatDay, unitName } from './insightFormat';

function ItemName({ item }) {
    return item.url ? (
        <Link href={item.url} className="hover:text-indigo-700 hover:underline">
            {item.label}
        </Link>
    ) : (
        item.label
    );
}

function Card({ title, description, children }) {
    return (
        <section className="min-w-0 bg-white p-4 shadow-sm sm:rounded-lg sm:p-6">
            <h3 className="text-base font-medium text-gray-900">{title}</h3>
            <p className="text-sm text-gray-600">{description}</p>
            {children}
        </section>
    );
}

function SavingRow({ item }) {
    return (
        <li className="flex items-start justify-between gap-3 py-3">
            <div className="min-w-0">
                <div className="truncate text-sm font-medium text-gray-900">
                    <ItemName item={item} />
                </div>
                <p className="text-xs text-gray-600">
                    <span className="font-medium text-gray-900">
                        {formatCurrency(item.price)} at {item.shop}
                    </span>
                    {item.kind === 'shop'
                        ? `, you usually pay ${formatCurrency(item.usual_price)} at ${item.usual_shop}`
                        : `, you usually pay ${formatCurrency(item.usual_price)}`}
                    {` per ${unitName(item.unit)}`}
                </p>
                {item.offer ? (
                    <div className="mt-0.5 flex min-w-0 flex-wrap items-center gap-x-1.5 text-xs text-gray-600">
                        <span>Offer:</span>
                        <OfferTitle offer={item.offer} className="max-w-full truncate text-indigo-700" />
                        {item.offer.valid_to && <span>until {formatDay(item.offer.valid_to)}</span>}
                    </div>
                ) : (
                    <p className="mt-0.5 truncate text-xs text-gray-600">Same product: {item.product_name}</p>
                )}
            </div>
            <div className="shrink-0 text-right">
                <div className="whitespace-nowrap text-sm font-semibold tabular-nums text-green-700">
                    Save {formatCurrency(item.saving)}
                </div>
                <div className="text-xs text-gray-600">on {item.amount_label}</div>
            </div>
        </li>
    );
}

function OffersNote({ status }) {
    if (status === 'compared') {
        return null;
    }

    return (
        <p className="mt-3 rounded-md bg-gray-50 p-3 text-xs text-gray-700">
            {status === 'other_currency' && 'Supermarket offers are in euros, so only your own prices are compared.'}
            {status === 'no_offers' && 'There are no offers for your area right now, so only your own prices are compared.'}
            {status === 'no_postal_code' && (
                <>
                    <Link href={route('deals')} className="font-medium text-indigo-700 hover:underline">
                        Add your postal code
                    </Link>{' '}
                    to compare this week&apos;s supermarket offers too.
                </>
            )}
        </p>
    );
}

function dueLabel(days) {
    if (days === 0) {
        return 'Today';
    }
    if (days === 1) {
        return 'Tomorrow';
    }
    if (days > 0) {
        return `In ${days} days`;
    }

    return days === -1 ? 'Expected yesterday' : `Expected ${-days} days ago`;
}

function DueRow({ item }) {
    const { cheaper } = item;

    return (
        <li className="flex items-start justify-between gap-3 py-3">
            <div className="min-w-0">
                <div className="truncate text-sm font-medium text-gray-900">
                    <ItemName item={item} />
                </div>
                <p className="text-xs text-gray-600">
                    <span className="font-medium text-gray-900">{dueLabel(item.days_until)}</span>
                    {item.every_days === 1 ? ' · every day' : ` · every ${item.every_days} days`}
                </p>
                {cheaper && (
                    <p className="text-xs font-medium text-green-700">
                        Cheaper at {cheaper.shop}
                        {cheaper.offer?.valid_to && ` until ${formatDay(cheaper.offer.valid_to)}`}
                    </p>
                )}
            </div>
            <div className="shrink-0 text-right">
                <div className="whitespace-nowrap text-sm font-semibold tabular-nums text-gray-900">
                    {formatCurrency(item.estimate)}
                </div>
                {item.amount_label && <div className="text-xs text-gray-600">usually {item.amount_label}</div>}
            </div>
        </li>
    );
}

/**
 * What to do with the next shop: where it could cost less and what is due.
 */
export default function InsightsNextSteps({ data, loading, failed, onRetry }) {
    if (failed) {
        return (
            <div className="flex items-center justify-between gap-3 bg-red-50 p-4 text-sm text-red-800 sm:rounded-md" role="alert">
                Savings and what to buy soon could not be loaded.
                <SecondaryButton type="button" onClick={onRetry}>
                    Try again
                </SecondaryButton>
            </div>
        );
    }

    if (!data) {
        return (
            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2" aria-label="Loading next steps">
                <div className="h-56 animate-pulse rounded-lg bg-gray-200" />
                <div className="h-56 animate-pulse rounded-lg bg-gray-200" />
            </div>
        );
    }

    const { savings, due_soon: dueSoon } = data;

    return (
        <div className={`grid grid-cols-1 items-start gap-5 transition-opacity lg:grid-cols-2 ${loading ? 'opacity-50' : ''}`}>
            <Card
                title="Save on your next shop"
                description={
                    savings.rows.length > 0
                        ? `Up to ${formatCurrency(savings.total)}, buying your usual amounts. Based on now, whatever period is chosen.`
                        : 'Based on now, whatever period is chosen.'
                }
            >
                {savings.rows.length === 0 ? (
                    <p className="py-8 text-center text-sm text-gray-600">
                        Nothing is clearly cheaper right now. Only equal amounts or identical products are compared.
                    </p>
                ) : (
                    <ul className="mt-2 divide-y divide-gray-100">
                        {savings.rows.map((item) => (
                            <SavingRow key={item.key} item={item} />
                        ))}
                    </ul>
                )}
                <OffersNote status={savings.offers_status} />
            </Card>

            <Card
                title="Likely to buy soon"
                description={
                    dueSoon.rows.length > 0
                        ? `About ${formatCurrency(dueSoon.total)} in the next 7 days, going by how often you buy.`
                        : 'The next 7 days, going by how often you buy.'
                }
            >
                {dueSoon.rows.length === 0 ? (
                    <p className="py-8 text-center text-sm text-gray-600">
                        Nothing is due. Things appear here once bought regularly on at least four days.
                    </p>
                ) : (
                    <ul className="mt-2 divide-y divide-gray-100">
                        {dueSoon.rows.map((item) => (
                            <DueRow key={item.key} item={item} />
                        ))}
                    </ul>
                )}
            </Card>
        </div>
    );
}
