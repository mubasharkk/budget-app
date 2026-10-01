import { ArrowDownLeftIcon, ArrowUpRightIcon } from '@heroicons/react/20/solid';

const KINDS = {
    income: {
        Icon: ArrowDownLeftIcon,
        label: 'Income',
        classes: 'bg-green-100 text-green-600',
    },
    expense: {
        Icon: ArrowUpRightIcon,
        label: 'Expense',
        classes: 'bg-red-100 text-red-600',
    },
};

export default function ReceiptKindIcon({ kind, className = '' }) {
    const { Icon, label, classes } = KINDS[kind] ?? KINDS.expense;

    return (
        <span
            role="img"
            aria-label={label}
            title={label}
            className={`inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full ${classes} ${className}`}
        >
            <Icon className="h-3.5 w-3.5" />
        </span>
    );
}
