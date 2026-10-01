import { BanknotesIcon } from '@heroicons/react/24/solid';

export default function IncomeBadge({ className = '' }) {
    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full bg-purple-600 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-white shadow-sm ${className}`}
        >
            <BanknotesIcon className="h-3.5 w-3.5" />
            Income
        </span>
    );
}
