export function EmptyState({ children }) {
    return <p className="py-12 text-center text-sm text-gray-400">{children}</p>;
}

export default function Panel({ title, children }) {
    return (
        <div className="overflow-hidden rounded-lg border border-gray-200 p-6">
            <h3 className="mb-4 text-base font-medium text-gray-900">{title}</h3>
            {children}
        </div>
    );
}
