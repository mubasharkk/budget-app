export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    icon: Icon,
    iconOnly = false,
    ...props
}) {
    return (
        <button
            {...props}
            type={type}
            className={
                `inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 ${
                    disabled && 'opacity-25'
                } ${iconOnly ? 'sm:px-4 px-2' : ''} ` + className
            }
            disabled={disabled}
        >
            {Icon && (
                <Icon className={`h-4 w-4 ${children ? 'mr-2 sm:mr-2 mr-0' : ''}`} />
            )}
            <span className={iconOnly ? 'hidden sm:inline' : ''}>{children}</span>
        </button>
    );
}
