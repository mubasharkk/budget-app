import { ArrowTopRightOnSquareIcon } from '@heroicons/react/24/outline';

/**
 * An offer's title; links to the retailer's page for the offer when the source provides one.
 */
export default function OfferTitle({ offer, className = '' }) {
    if (!offer.external_url) {
        return (
            <span className={className} title={offer.title}>
                {offer.title}
            </span>
        );
    }

    return (
        <a
            href={offer.external_url}
            target="_blank"
            rel="noopener noreferrer nofollow"
            title={`View at ${offer.retailer_label}`}
            className={`inline-flex min-w-0 items-center gap-1 hover:text-indigo-700 hover:underline ${className}`}
        >
            <span className="truncate">{offer.title}</span>
            <ArrowTopRightOnSquareIcon className="h-3.5 w-3.5 flex-shrink-0" aria-label="opens in a new tab" />
        </a>
    );
}
