import { formatCurrency } from '@/utils/money';

function Stat({ label, value, detail, tone = 'text-gray-900' }) {
    return (
        <div className="rounded-lg bg-white p-5 shadow-sm">
            <div className="text-sm font-medium text-gray-500">{label}</div>
            <div className={`mt-1 text-2xl font-semibold ${tone}`}>{value}</div>
            <div className="text-xs text-gray-500">{detail}</div>
        </div>
    );
}

const countLabel = (count) => `${count} ${count === 1 ? 'task' : 'tasks'}`;

/**
 * Open tasks and the amounts still to pay, overall and past their deadline,
 * and what finished tasks have cost so far.
 */
export default function TaskSummary({ summary }) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Stat
                label="Still to pay"
                value={formatCurrency(summary.open_amount)}
                detail={`across ${countLabel(summary.open_count)} open`}
            />
            <Stat
                label="Past deadline"
                value={formatCurrency(summary.overdue_amount)}
                detail={countLabel(summary.overdue_count)}
                tone={summary.overdue_count > 0 ? 'text-red-700' : 'text-gray-900'}
            />
            <Stat
                label="Already spent"
                value={formatCurrency(summary.done_amount)}
                detail={`on ${countLabel(summary.done_count)} done`}
                tone="text-green-700"
            />
        </div>
    );
}
