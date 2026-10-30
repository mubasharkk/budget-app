import { Link } from '@inertiajs/react';

export default function ProductLink({ productId, children, className = 'text-sm font-medium' }) {
    return (
        <Link
            href={route('products.show', productId)}
            className={`${className} text-indigo-600 hover:text-indigo-800`}
        >
            {children}
        </Link>
    );
}
