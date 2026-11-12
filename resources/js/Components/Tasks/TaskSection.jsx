import TaskRow from './TaskRow';

const TONES = {
    red: 'text-red-700',
    blue: 'text-blue-700',
    yellow: 'text-yellow-700',
    gray: 'text-gray-500',
};

/**
 * A titled group of tasks on the Tasks page; renders nothing when empty.
 */
export default function TaskSection({ title, tasks, tone = 'gray', onEdit }) {
    if (tasks.length === 0) {
        return null;
    }

    return (
        <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <h3
                className={`border-b border-gray-100 px-4 py-2 text-xs font-semibold uppercase tracking-wider ${TONES[tone]}`}
            >
                {title} <span className="font-normal">· {tasks.length}</span>
            </h3>
            <ul className="divide-y divide-gray-100">
                {tasks.map((task) => (
                    <TaskRow key={task.id} task={task} onEdit={onEdit} />
                ))}
            </ul>
        </section>
    );
}
