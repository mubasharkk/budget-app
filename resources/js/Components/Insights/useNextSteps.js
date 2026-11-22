import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';

/**
 * Savings, what is due and what to fix. They follow the category, not the
 * period, so they reload only when that or the underlying data changes.
 */
export default function useNextSteps(categoryId) {
    const [version, setVersion] = useState(0);
    const [state, setState] = useState({ data: null, loading: true, failed: false });

    useEffect(() => {
        let current = true;
        setState((previous) => ({ ...previous, loading: true, failed: false }));

        axios
            .get(route('insights.next-steps'), { params: categoryId ? { category_id: categoryId } : {} })
            .then((res) => current && setState({ data: res.data, loading: false, failed: false }))
            .catch(() => current && setState({ data: null, loading: false, failed: true }));

        return () => {
            current = false;
        };
    }, [categoryId, version]);

    const reload = useCallback(() => setVersion((value) => value + 1), []);

    return { ...state, reload };
}
