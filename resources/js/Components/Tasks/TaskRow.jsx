import { Link, router } from '@inertiajs/react';
import {
    ArrowPathIcon,
    LinkIcon,
    PencilSquareIcon,
    TrashIcon,
} from '@heroicons/react/24/outline';
import Checkbox from '@/Components/Checkbox';
import { formatCurrency } from '@/utils/money';
import { dueLabel, dueTone, formatDueDate } from './taskDates';

const PRIORITY_BADGE = {
    high: { label: 'High', classes: 'bg-red-50 text-red-700 ring-red-600/20' },
    normal: { label: 'Normal', classes: 'bg-gray-50 text-gray-600 ring-gray-500/20' },
    low: { label: 'Low', classes: 'bg-white text-gray-400 ring-gray-300' },
};

/**
 * One task: tick it off, see when it's due and what it's about, edit or delete it.
 */
export default function TaskRow({ task, onEdit, onChanged, showLink = true }) {
    const visit = { preserveScroll: true, onSuccess: () => onChanged?.() };

    const toggle = () =>
        router.patch(
            route(task.is_done ? 'tasks.reopen' : 'tasks.complete', task.id),
            {},
            visit,
        );

    const remove = () => {
        if (window.confirm(`Delete “${task.title}”?`)) {
            router.delete(route('tasks.destroy', task.id), visit);
        }
    };

    return (
        <li className="flex items-start gap-3 px-4 py-3">
            <Checkbox
                className="mt-1"
                checked={task.is_done}
                onChange={toggle}
                aria-label={task.is_done ? 'Mark as not done' : 'Mark as done'}
            />

            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <span
                        className={`truncate text-sm font-medium ${
                            task.is_done ? 'text-gray-400 line-through' : 'text-gray-900'
                        }`}
                    >
                        {task.title}
                    </span>
                </div>

                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                    {!task.is_done && PRIORITY_BADGE[task.priority] && (
                        <span
                            className={`rounded-full px-2 py-0.5 font-medium ring-1 ring-inset ${PRIORITY_BADGE[task.priority].classes}`}
                        >
                            {PRIORITY_BADGE[task.priority].label} priority
                        </span>
                    )}
                    {task.due_date && (
                        <span
                            className={`rounded-full px-2 py-0.5 font-medium ${dueTone(task.due_date, task.is_done)}`}
                            title={`Deadline: ${formatDueDate(task.due_date)}`}
                        >
                            {task.is_done ? formatDueDate(task.due_date) : dueLabel(task.due_date)}
                        </span>
                    )}
                    {task.repeats_label && (
                        <span className="inline-flex items-center gap-1">
                            <ArrowPathIcon className="h-3.5 w-3.5" />
                            {task.repeats_label}
                        </span>
                    )}
                    {task.amount && (
                        <span className="font-semibold text-gray-700" title="Amount payable or cost">
                            {formatCurrency(task.amount)}
                        </span>
                    )}
                    {showLink && task.link && (
                        <Link
                            href={task.link.url}
                            className="inline-flex max-w-xs items-center gap-1 truncate text-indigo-600 hover:text-indigo-800"
                        >
                            <LinkIcon className="h-3.5 w-3.5 shrink-0" />
                            <span className="truncate">{task.link.label}</span>
                        </Link>
                    )}
                </div>

                {task.notes && !task.is_done && (
                    <p className="mt-1 whitespace-pre-line text-sm text-gray-500">
                        {task.notes}
                    </p>
                )}
            </div>

            <div className="flex shrink-0 items-center gap-2">
                {onEdit && (
                    <button
                        type="button"
                        onClick={() => onEdit(task)}
                        className="text-gray-400 hover:text-gray-700"
                        title="Edit"
                    >
                        <PencilSquareIcon className="h-5 w-5" />
                    </button>
                )}
                <button
                    type="button"
                    onClick={remove}
                    className="text-gray-400 hover:text-red-600"
                    title="Delete"
                >
                    <TrashIcon className="h-5 w-5" />
                </button>
            </div>
        </li>
    );
}
