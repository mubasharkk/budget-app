import { useState } from 'react';

/**
 * A provider's logo, or the first letter of the name when there is no logo
 * or it fails to load.
 */
export default function ProviderLogo({ logo, name, className = 'h-9 w-9' }) {
    const [failed, setFailed] = useState(false);

    if (!logo || failed) {
        return (
            <div
                className={`flex shrink-0 items-center justify-center rounded bg-gray-100 text-xs font-medium uppercase text-gray-500 ${className}`}
                aria-hidden="true"
            >
                {(name ?? '?').charAt(0)}
            </div>
        );
    }

    return (
        <img
            src={logo}
            alt=""
            className={`shrink-0 rounded border border-gray-100 bg-white object-contain ${className}`}
            onError={() => setFailed(true)}
        />
    );
}
