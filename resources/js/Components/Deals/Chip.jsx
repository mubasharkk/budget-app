export default function Chip({ active, onClick, disabled = false, title, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={title}
            aria-pressed={active}
            className={`rounded-full px-3 py-1 text-sm font-medium transition disabled:cursor-not-allowed disabled:text-gray-400 disabled:line-through ${
                active ? 'bg-brand-primary text-white' : 'bg-gray-100 text-gray-700 enabled:hover:bg-gray-200'
            }`}
        >
            {children}
        </button>
    );
}
