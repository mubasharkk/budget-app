import { useCallback, useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import TaskRow from '@/Components/Tasks/TaskRow';

/**
 * Dashboard section: overdue tasks and those due in the next week; tick them off in place.
 */
export default function TasksWidget() {
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);

    const load = useCallback(() => {
        axios
            .get(route('dashboard.tasks'))
            .then((response) => {
                setData(response.data);
                setError(null);
            })
            .catch(() => setError('Could not load your tasks.'));
    }, []);

    useEffect(load, [load]);

    const tasks = data?.tasks ?? [];

    return (
        <div className="p-6">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-lg font-medium text-gray-900">Tasks due soon</h3>
                    <p className="mt-1 text-sm text-gray-500">
                        Overdue and due in the next 7 days.
                    </p>
                </div>
                {data?.overdue_count > 0 && (
                    <span className="whitespace-nowrap rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                        {data.overdue_count} overdue
                    </span>
                )}
            </div>

            {error && <p className="mt-3 text-sm text-red-600">{error}</p>}

            <div className="mt-4 max-h-80 overflow-y-auto rounded-lg border border-gray-100">
                {!data && !error ? (
                    <div className="space-y-3 p-4">
                        {[0, 1, 2].map((row) => (
                            <div key={row} className="h-6 animate-pulse rounded bg-gray-100" />
                        ))}
                    </div>
                ) : tasks.length === 0 ? (
                    <p className="p-6 text-center text-sm text-gray-500">
                        Nothing due this week.
                    </p>
                ) : (
                    <ul className="divide-y divide-gray-100">
                        {tasks.map((task) => (
                            <TaskRow key={task.id} task={task} onChanged={load} />
                        ))}
                    </ul>
                )}
            </div>

            <Link
                href={route('tasks.index')}
                className="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-800"
            >
                All tasks{data ? ` (${data.open_count} open)` : ''} →
            </Link>
        </div>
    );
}
