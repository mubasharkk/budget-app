import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TaskForm from './TaskForm';

const fieldsFor = (task, link) => ({
    title: task?.title ?? '',
    notes: task?.notes ?? '',
    due_date: task?.due_date ?? '',
    priority: task?.priority ?? 'normal',
    amount: task?.amount ?? '',
    repeats: task?.repeats ?? '',
    link_type: task?.link?.type ?? link?.type ?? '',
    link_id: task?.link?.id ?? link?.id ?? '',
});

/**
 * Add a task, or edit `task` when given. `link` ({type, id}) pins a new task to a record.
 */
export default function TaskFormModal({ show, onClose, task = null, link = null, options }) {
    const { data, setData, post, put, processing, errors, reset, clearErrors, setDefaults } =
        useForm(fieldsFor(task, link));

    useEffect(() => {
        if (show) {
            const fields = fieldsFor(task, link);
            setDefaults(fields);
            setData(fields);
            clearErrors();
        }
    }, [show, task?.id]);

    const close = () => {
        reset();
        onClose();
    };

    const submit = (e) => {
        e.preventDefault();
        e.stopPropagation();
        const visit = { preserveScroll: true, onSuccess: close };

        if (task) {
            put(route('tasks.update', task.id), visit);
        } else {
            post(route('tasks.store'), visit);
        }
    };

    return (
        <Modal show={show} onClose={close} maxWidth="xl">
            <form onSubmit={submit} className="p-6">
                <h2 className="text-lg font-medium text-gray-900">
                    {task ? 'Edit task' : 'New task'}
                </h2>

                <div className="mt-4">
                    <TaskForm data={data} setData={setData} errors={errors} options={options} />
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={close}>Cancel</SecondaryButton>
                    <PrimaryButton disabled={processing}>
                        {task ? 'Save changes' : 'Add task'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
