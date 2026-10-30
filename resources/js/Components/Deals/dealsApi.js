import { useEffect, useState } from 'react';
import axios from 'axios';

const errorMessage = (error) =>
    error.response?.data?.message ?? 'Deals could not be loaded. Please try again.';

/**
 * "For you" offers for the dashboard widget, which loads its data as JSON like
 * every other dashboard section. The Deals page itself gets the same data as props.
 */
export function useDealsForYou(limit, enabled = true) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(enabled);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!enabled) {
            return undefined;
        }

        let cancelled = false;
        setLoading(true);
        setError(null);

        axios
            .get(route('deals.for-you'), { params: { limit } })
            .then((response) => !cancelled && setData(response.data))
            .catch((err) => !cancelled && setError(errorMessage(err)))
            .finally(() => !cancelled && setLoading(false));

        return () => {
            cancelled = true;
        };
    }, [limit, enabled]);

    return { data, loading, error };
}
