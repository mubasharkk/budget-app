import { daysUntil } from '@/utils/deals';

const LABELS = { 0: 'Ends today', 1: 'Ends tomorrow' };

export default function EndsBadge({ validTo }) {
    const days = daysUntil(validTo);

    if (days === null || days > 1 || days < 0) {
        return null;
    }

    return (
        <span className="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
            {LABELS[days]}
        </span>
    );
}
