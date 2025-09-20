export default function PrimaryButton({
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
            className={
                `inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900 ${
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
