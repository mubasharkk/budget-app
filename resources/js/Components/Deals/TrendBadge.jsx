const STYLES = {
    rising: 'bg-red-100 text-red-700',
    falling: 'bg-green-100 text-green-700',
    stable: 'bg-gray-100 text-gray-600',
};

const LABELS = { rising: 'Rising', falling: 'Falling', stable: 'Stable' };

export default function TrendBadge({ direction }) {
    return (
        <span
            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${STYLES[direction] ?? STYLES.stable}`}
        >
            {LABELS[direction] ?? direction}
        </span>
    );
}
