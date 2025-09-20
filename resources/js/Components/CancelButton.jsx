import React from 'react';

export default function CancelButton({
    className = '',
    children = 'Cancel',
    variant = 'button',
    href,
    icon: Icon,
    iconOnly = false,
    ...props
}) {
    const baseClasses = 'px-4 py-2 text-xs font-medium uppercase inline-flex items-center';
    const buttonClasses = 'bg-gray-300 text-gray-800 rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300';
    const linkClasses = 'text-gray-600 hover:text-gray-900';

    const classes = variant === 'link'
        ? `${baseClasses} ${linkClasses} ${className} ${iconOnly ? 'sm:px-4 px-2' : ''}`
        : `${baseClasses} ${buttonClasses} ${className} ${iconOnly ? 'sm:px-4 px-2' : ''}`;

    const content = (
        <>
            {Icon && (
                <Icon className={`h-4 w-4 ${children ? 'mr-2 sm:mr-2 mr-0' : ''}`} />
            )}
            <span className={iconOnly ? 'hidden sm:inline' : ''}>{children}</span>
        </>
    );

    if (variant === 'link' && href) {
        return (
            <a
                href={href}
                className={classes}
                {...props}
            >
                {content}
            </a>
        );
    }

    return (
        <button
            className={classes}
            {...props}
        >
            {content}
        </button>
    );
}
