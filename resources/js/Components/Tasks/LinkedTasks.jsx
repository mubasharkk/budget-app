import { useState } from 'react';
import { PlusIcon } from '@heroicons/react/24/outline';
import SecondaryButton from '@/Components/SecondaryButton';
import TaskFormModal from './TaskFormModal';
import TaskRow from './TaskRow';

/**
 * The tasks about one receipt, contract, budget or savings entry, shown on its page.
 *
 * @param link {type, id} of the record new tasks are linked to
 */
export default function LinkedTasks({ tasks = [], link, priorities, repeatOptions, linkTypes }) {
    const [editing, setEditing] = useState(null);
    const [open, setOpen] = useState(false);
    const options = { priorities, repeatOptions, linkTypes };

    const edit = (task) => {
        setEditing(task);
        setOpen(true);
    };

    const add = () => {
        setEditing(null);
        setOpen(true);
    };

    return (
        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <h3 className="text-sm font-semibold text-gray-900">Tasks</h3>
                <SecondaryButton icon={PlusIcon} onClick={add}>
                    Add task
                </SecondaryButton>
            </div>

            {tasks.length === 0 ? (
                <p className="px-4 py-6 text-center text-sm text-gray-500">
                    Nothing to do here yet. Add a reminder like “Cancel before the
                    renewal” or “Find the warranty”.
                </p>
            ) : (
                <ul className="divide-y divide-gray-100">
                    {tasks.map((task) => (
                        <TaskRow key={task.id} task={task} onEdit={edit} showLink={false} />
                    ))}
                </ul>
            )}

            <TaskFormModal
                show={open}
                onClose={() => setOpen(false)}
                task={editing}
                link={link}
                options={options}
            />
        </div>
    );
}
