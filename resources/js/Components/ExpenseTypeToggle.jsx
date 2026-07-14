const OPTIONS = [
    { value: 'personal', label: 'Personal' },
    { value: 'business', label: 'Business' },
];

export default function ExpenseTypeToggle({ value, onChange, className = '' }) {
    return (
        <div
            className={`inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 ${className}`}
        >
            {OPTIONS.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    onClick={() => onChange(option.value)}
                    className={`rounded-md px-4 py-1.5 text-sm font-medium transition ${
                        value === option.value
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-500 hover:text-gray-800'
                    }`}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}
