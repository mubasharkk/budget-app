import { ArrowTopRightOnSquareIcon } from '@heroicons/react/24/outline';

/**
 * An offer's title, linking to the retailer's page for the offer or, failing that,
 * to the offer on marktguru (the data source).
 */
export default function OfferTitle({ offer, className = '' }) {
    const href = offer.external_url ?? offer.source_url;

    if (!href) {
        return (
            <span className={className} title={offer.title}>
                {offer.title}
            </span>
        );
    }

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer nofollow"
            title={offer.external_url ? `View at ${offer.retailer_label}` : 'View on marktguru'}
            className={`inline-flex min-w-0 items-center gap-1 hover:text-indigo-700 hover:underline ${className}`}
        >
            <span className="truncate">{offer.title}</span>
            <ArrowTopRightOnSquareIcon className="h-3.5 w-3.5 flex-shrink-0" aria-label="opens in a new tab" />
        </a>
    );
}
