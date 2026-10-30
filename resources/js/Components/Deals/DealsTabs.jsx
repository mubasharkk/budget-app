export default function DealsTabs({ tabs, active, onSelect }) {
    return (
        <div role="tablist" className="flex gap-6 border-b border-gray-200">
            {tabs.map((tab) => (
                <button
                    key={tab.value}
                    type="button"
                    role="tab"
                    aria-selected={active === tab.value}
                    onClick={() => onSelect(tab.value)}
                    className={`-mb-px border-b-2 pb-3 text-sm font-medium transition ${
                        active === tab.value
                            ? 'border-brand-primary text-brand-primary'
                            : 'border-transparent text-gray-500 hover:text-gray-700'
                    }`}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}
