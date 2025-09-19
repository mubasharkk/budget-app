import React from 'react';

export default function CancelButton({ 
    className = '', 
    children = 'Cancel', 
    variant = 'button',
    href,
    ...props 
}) {
    const baseClasses = 'px-4 py-2 text-base font-medium uppercase';
    const buttonClasses = 'bg-gray-300 text-gray-800 rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300';
    const linkClasses = 'text-gray-600 hover:text-gray-900';
    
    const classes = variant === 'link' 
        ? `${baseClasses} ${linkClasses} ${className}`
        : `${baseClasses} ${buttonClasses} ${className}`;

    if (variant === 'link' && href) {
        return (
            <a
                href={href}
                className={classes}
                {...props}
            >
                {children}
            </a>
        );
    }

    return (
        <button
            className={classes}
            {...props}
        >
            {children}
        </button>
    );
}