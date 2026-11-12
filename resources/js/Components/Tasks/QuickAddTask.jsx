import { useForm } from '@inertiajs/react';
import { PlusIcon } from '@heroicons/react/24/outline';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';

const selectClasses =
    'block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-32';

/**
 * Add a task in one line: what, priority, deadline and the amount to pay.
 */
export default function QuickAddTask({ priorities }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        priority: 'normal',
        due_date: '',
        amount: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('tasks.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="rounded-lg bg-white p-4 shadow-sm">
            <div className="flex flex-col gap-3 lg:flex-row">
                <TextInput
                    className="block w-full flex-1"
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    placeholder="Add a task, e.g. Pay the plumber"
                    aria-label="New task"
                />
                <div className="flex flex-col gap-3 sm:flex-row">
                    <select
                        className={selectClasses}
                        value={data.priority}
                        onChange={(e) => setData('priority', e.target.value)}
                        aria-label="Priority"
                    >
                        {priorities.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label} priority
                            </option>
                        ))}
                    </select>
                    <TextInput
                        type="date"
                        className="block w-full sm:w-40"
                        value={data.due_date}
                        onChange={(e) => setData('due_date', e.target.value)}
                        aria-label="Deadline"
                        title="Deadline"
                    />
                    <TextInput
                        type="number"
                        step="0.01"
                        min="0.01"
                        className="block w-full sm:w-32"
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                        placeholder="Amount"
                        aria-label="Amount payable or cost"
                        title="Amount payable or cost"
                    />
                    <PrimaryButton icon={PlusIcon} disabled={processing || !data.title.trim()}>
                        Add
                    </PrimaryButton>
                </div>
            </div>
            <InputError
                message={errors.title ?? errors.due_date ?? errors.amount ?? errors.priority}
                className="mt-2"
            />
        </form>
    );
}
