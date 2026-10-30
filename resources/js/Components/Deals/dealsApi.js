import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';

const SYNC_POLL_MS = 15000;
const SYNC_POLL_LIMIT = 20;

const errorMessage = (error) =>
    error.response?.data?.message ?? 'Deals could not be loaded. Please try again.';

export const fetchDealOffers = (params) =>
    axios.get(route('deals.offers'), { params }).then((response) => response.data);

export const fetchDealsForYou = (limit) =>
    axios.get(route('deals.for-you'), { params: { limit } }).then((response) => response.data);

/**
 * Loads data with a fetcher and re-polls while the postal code has not synced yet.
 */
function useSyncedResource(fetcher, dependencies, enabled) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(enabled);
    const [error, setError] = useState(null);
    const [polls, setPolls] = useState(0);

    const load = useCallback(() => {
        if (!enabled) {
            return undefined;
        }

        let cancelled = false;
        setError(null);

        fetcher()
            .then((result) => !cancelled && setData(result))
            .catch((err) => !cancelled && setError(errorMessage(err)))
            .finally(() => !cancelled && setLoading(false));

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [enabled, ...dependencies]);

    useEffect(() => {
        setLoading(true);

        return load();
    }, [load]);

    useEffect(() => {
        if (!data || data.synced_at || polls >= SYNC_POLL_LIMIT) {
            return undefined;
        }

        const timer = setTimeout(() => {
            setPolls((count) => count + 1);
            load();
        }, SYNC_POLL_MS);

        return () => clearTimeout(timer);
    }, [data, polls, load]);

    return { data, loading, error };
}

export function useDealOffers(params, enabled = true) {
    const key = JSON.stringify(params);

    return useSyncedResource(() => fetchDealOffers(params), [key], enabled);
}

export function useDealsForYou(limit, enabled = true) {
    return useSyncedResource(() => fetchDealsForYou(limit), [limit], enabled);
}
