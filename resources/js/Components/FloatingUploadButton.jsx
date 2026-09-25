import { ArrowUpTrayIcon } from '@heroicons/react/24/outline';
import { Link } from '@inertiajs/react';

const HIDDEN_ON = ['receipts.create', 'receipts.scan'];

export default function FloatingUploadButton() {
    if (HIDDEN_ON.some((name) => route().current(name))) {
        return null;
    }

    return (
        <Link
            href={route('receipts.create')}
            className="group fixed bottom-6 right-6 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-brand-primary text-white shadow-lg transition hover:bg-brand-dark focus:outline-none focus:ring-2 focus:ring-brand-mid focus:ring-offset-2"
            aria-label="Upload receipt"
            title="Upload receipt"
        >
            <ArrowUpTrayIcon className="h-7 w-7" />
        </Link>
    );
}
