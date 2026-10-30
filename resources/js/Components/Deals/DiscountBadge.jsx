export default function DiscountBadge({ percent }) {
    if (!percent) {
        return null;
    }

    return (
        <span className="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">
            −{percent}%
        </span>
    );
}
