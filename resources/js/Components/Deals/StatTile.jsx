export default function StatTile({ label, value, tone = 'default' }) {
    const valueClass = tone === 'positive' ? 'text-green-700' : 'text-gray-900';

    return (
        <div className="rounded-lg bg-gray-50 px-4 py-3">
            <p className="text-sm text-gray-500">{label}</p>
            <p className={`text-xl font-semibold ${valueClass}`}>{value}</p>
        </div>
    );
}
