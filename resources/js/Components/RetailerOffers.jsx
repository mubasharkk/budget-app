import { useEffect, useState } from 'react';
import axios from 'axios';
import { usePage } from '@inertiajs/react';
import PostalCodeForm from '@/Components/PostalCodeForm';
import TextInput from '@/Components/TextInput';
import { formatCurrency } from '@/utils/money';

const formatDate = (value) =>
    value
        ? new Date(value).toLocaleDateString('de-DE', {
              day: '2-digit',
              month: '2-digit',
          })
        : null;

function RetailerChip({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-full px-3 py-1 text-sm font-medium transition ${
                active
                    ? 'bg-brand-primary text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
        >
            {children}
        </button>
    );
}

function OfferCard({ offer }) {
    const validTo = formatDate(offer.valid_to);

    return (
        <li className="flex flex-col gap-2 rounded-lg border border-gray-100 p-4">
            <div className="flex items-center justify-between gap-2">
                <span className="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">
                    {offer.retailer_label}
                </span>
                {offer.discount_percent ? (
                    <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">
                        −{offer.discount_percent}%
                    </span>
                ) : null}
            </div>
            <div className="min-w-0">
                {offer.brand && (
                    <p className="text-xs uppercase tracking-wide text-gray-500">
                        {offer.brand}
                    </p>
                )}
                <p className="text-sm font-medium text-gray-900">{offer.title}</p>
                {offer.description && (
                    <p className="mt-0.5 line-clamp-2 text-xs text-gray-500">
                        {offer.description}
                    </p>
                )}
            </div>
            <div className="mt-auto flex items-end justify-between gap-2">
                <div className="flex items-baseline gap-2">
                    <span className="text-lg font-semibold text-gray-900">
                        {formatCurrency(offer.price)}
                    </span>
                    {offer.old_price ? (
                        <span className="text-xs text-gray-400 line-through">
                            {formatCurrency(offer.old_price)}
                        </span>
                    ) : null}
                    {offer.unit && (
                        <span className="text-xs text-gray-500">/ {offer.unit}</span>
                    )}
                </div>
                {validTo && (
                    <span className="text-xs text-gray-500">until {validTo}</span>
                )}
            </div>
        </li>
    );
}

export default function RetailerOffers({ retailers = [] }) {
    const postalCode = usePage().props.auth.user.postal_code;
    const [editingPostalCode, setEditingPostalCode] = useState(false);
    const [retailer, setRetailer] = useState('');
    const [search, setSearch] = useState('');
    const [query, setQuery] = useState('');
    const [offers, setOffers] = useState([]);
    const [syncedAt, setSyncedAt] = useState(null);
    const [pollCount, setPollCount] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!postalCode) {
            return;
        }

        if (pollCount === 0) setLoading(true);
        setError(null);

        const params = {};
        if (query) params.q = query;
        if (retailer) params.retailer = retailer;

        axios
            .get(route('deals.offers'), { params })
            .then((res) => {
                setOffers(res.data.offers ?? []);
                setSyncedAt(res.data.synced_at ?? null);
            })
            .catch((err) =>
                setError(
                    err.response?.data?.message ??
                        'Deals could not be loaded. Please try again.',
                ),
            )
            .finally(() => setLoading(false));
    }, [postalCode, retailer, query, pollCount]);

    useEffect(() => {
        setPollCount(0);
    }, [postalCode]);

    useEffect(() => {
        if (!postalCode || loading || error || syncedAt || pollCount >= 20) {
            return;
        }

        const timer = setTimeout(() => setPollCount((n) => n + 1), 15000);

        return () => clearTimeout(timer);
    }, [postalCode, loading, error, syncedAt, pollCount]);

    if (!postalCode || editingPostalCode) {
        return (
            <section className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 className="text-base font-medium text-gray-900">
                    Supermarket deals near you
                </h3>
                <p className="mt-1 text-sm text-gray-600">
                    Offers at Lidl, Aldi, Netto, Kaufland and Edeka differ by
                    region. Enter your postal code to see this week's deals.
                </p>
                <div className="mt-4">
                    <PostalCodeForm
                        isFocused
                        onSaved={() => setEditingPostalCode(false)}
                        onCancel={
                            postalCode ? () => setEditingPostalCode(false) : null
                        }
                    />
                </div>
            </section>
        );
    }

    const submitSearch = (e) => {
        e.preventDefault();
        setQuery(search.trim());
    };

    return (
        <section className="flex flex-col gap-4 overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-base font-medium text-gray-900">
                    Supermarket deals near {postalCode}
                </h3>
                <button
                    type="button"
                    onClick={() => setEditingPostalCode(true)}
                    className="text-sm text-indigo-600 hover:text-indigo-800"
                >
                    Change postal code
                </button>
            </div>

            <div className="flex flex-wrap gap-2">
                <RetailerChip active={retailer === ''} onClick={() => setRetailer('')}>
                    All
                </RetailerChip>
                {retailers.map((option) => (
                    <RetailerChip
                        key={option.value}
                        active={retailer === option.value}
                        onClick={() => setRetailer(option.value)}
                    >
                        {option.label}
                    </RetailerChip>
                ))}
            </div>

            <form onSubmit={submitSearch} className="flex gap-2">
                <TextInput
                    type="search"
                    className="block w-full sm:max-w-sm"
                    placeholder="Search offers, e.g. Eier, Kaffee"
                    value={search}
                    onChange={(e) => {
                        setSearch(e.target.value);
                        if (e.target.value === '') setQuery('');
                    }}
                />
            </form>

            {loading ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {[...Array(6)].map((_, i) => (
                        <div key={i} className="h-36 animate-pulse rounded-lg bg-gray-200" />
                    ))}
                </div>
            ) : error ? (
                <p className="py-12 text-center text-sm text-red-600">{error}</p>
            ) : !syncedAt ? (
                <p className="py-12 text-center text-sm text-gray-500">
                    Fetching this week's deals for {postalCode}… this usually
                    takes a minute.
                </p>
            ) : offers.length === 0 ? (
                <p className="py-12 text-center text-sm text-gray-400">
                    No current offers found{query ? ` for “${query}”` : ''}.
                </p>
            ) : (
                <>
                    <p className="text-xs text-gray-500">
                        {offers.length} offers · updated{' '}
                        {new Date(syncedAt).toLocaleString('de-DE', {
                            day: '2-digit',
                            month: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                        })}{' '}
                        · source: marktguru
                    </p>
                    <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {offers.map((offer) => (
                            <OfferCard key={offer.id} offer={offer} />
                        ))}
                    </ul>
                </>
            )}
        </section>
    );
}
