import TaskRow from './TaskRow';
import { formatCurrency } from '@/utils/money';

const TONES = {
    red: 'text-red-700',
    blue: 'text-blue-700',
    yellow: 'text-yellow-700',
    gray: 'text-gray-500',
};

/**
 * A titled group of tasks on the Tasks page; renders nothing when empty.
 */
export default function TaskSection({ title, tasks, tone = 'gray', total = null, onEdit }) {
    if (tasks.length === 0) {
        return null;
    }

    return (
        <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-2">
                <h3 className={`text-xs font-semibold uppercase tracking-wider ${TONES[tone]}`}>
                    {title} <span className="font-normal">· {tasks.length}</span>
                </h3>
                {total && (
                    <span className="text-sm text-gray-500">
                        {total.label}{' '}
                        <span className="font-semibold tabular-nums text-gray-900">{formatCurrency(total.amount)}</span>
                    </span>
                )}
            </div>
            <ul className="divide-y divide-gray-100">
                {tasks.map((task) => (
                    <TaskRow key={task.id} task={task} onEdit={onEdit} />
                ))}
            </ul>
        </section>
    );
}
