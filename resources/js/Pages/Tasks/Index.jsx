import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { PlusIcon } from '@heroicons/react/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import QuickAddTask from '@/Components/Tasks/QuickAddTask';
import TaskFormModal from '@/Components/Tasks/TaskFormModal';
import TaskSection from '@/Components/Tasks/TaskSection';
import TaskSummary from '@/Components/Tasks/TaskSummary';

const SECTIONS = [
    { key: 'high', title: 'High priority', tone: 'red' },
    { key: 'normal', title: 'Normal priority', tone: 'blue' },
    { key: 'low', title: 'Low priority', tone: 'yellow' },
    { key: 'done', title: 'Recently done' },
];

export default function Index({ board, summary, priorities, repeatOptions, linkTypes, linkOptions }) {
    const flash = usePage().props.flash ?? {};
    const [editing, setEditing] = useState(null);
    const [open, setOpen] = useState(false);
    const options = { priorities, repeatOptions, linkTypes, linkOptions };
    const isEmpty = SECTIONS.every(({ key }) => board[key].length === 0);

    const openForm = (task = null) => {
        setEditing(task);
        setOpen(true);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Tasks</h2>
                    <PrimaryButton icon={PlusIcon} onClick={() => openForm()}>
                        New task
                    </PrimaryButton>
                </div>
            }
        >
            <Head title="Tasks" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    <TaskSummary summary={summary} />

                    <QuickAddTask priorities={priorities} />

                    {isEmpty ? (
                        <div className="rounded-lg bg-white p-10 text-center shadow-sm">
                            <p className="text-gray-500">
                                Nothing on your list. Add money to-dos here — pay a
                                bill, cancel a contract, file a tax return — and link
                                them to the receipt, contract, budget or savings
                                entry they are about.
                            </p>
                        </div>
                    ) : (
                        SECTIONS.map(({ key, title, tone }) => (
                            <TaskSection
                                key={key}
                                title={title}
                                tone={tone}
                                tasks={board[key]}
                                total={
                                    key === 'done'
                                        ? { label: 'Spent so far', amount: summary.done_amount }
                                        : null
                                }
                                onEdit={openForm}
                            />
                        ))
                    )}
                </div>
            </div>

            <TaskFormModal
                show={open}
                onClose={() => setOpen(false)}
                task={editing}
                options={options}
            />
        </AuthenticatedLayout>
    );
}
