import { useEffect } from 'react';
import { usePoll } from '@inertiajs/react';
import { OFFER_POLL_PROPS } from '@/Components/Deals/visitDeals';

const POLL_MS = 15000;
const GIVE_UP_MS = 5 * 60 * 1000;

/**
 * Reloads the offer props every 15 s while this postal code's first sync is
 * still running, and gives up after five minutes.
 */
export default function useSyncPolling(waitingForSync) {
    const { start, stop } = usePoll(POLL_MS, { only: OFFER_POLL_PROPS }, { autoStart: false });

    useEffect(() => {
        if (!waitingForSync) {
            stop();

            return undefined;
        }

        start();
        const giveUp = setTimeout(stop, GIVE_UP_MS);

        return () => {
            clearTimeout(giveUp);
            stop();
        };
    }, [waitingForSync, start, stop]);
}
