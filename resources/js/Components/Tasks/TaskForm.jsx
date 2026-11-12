import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import TaskLinkPicker from './TaskLinkPicker';

const selectClasses =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

/**
 * The task fields. Without linkOptions (e.g. on a contract page) the link is fixed and not shown.
 */
export default function TaskForm({ data, setData, errors, options }) {
    const { priorities, repeatOptions, linkTypes, linkOptions } = options;

    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
                <InputLabel htmlFor="title" value="Task" />
                <TextInput
                    id="title"
                    className="mt-1 block w-full"
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    placeholder="e.g. Cancel the gym contract"
                    isFocused
                />
                <InputError message={errors.title} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="due_date" value="Deadline" />
                <TextInput
                    id="due_date"
                    type="date"
                    className="mt-1 block w-full"
                    value={data.due_date}
                    onChange={(e) => setData('due_date', e.target.value)}
                />
                <InputError message={errors.due_date} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="repeats" value="Repeats" />
                <select
                    id="repeats"
                    className={selectClasses}
                    value={data.repeats}
                    onChange={(e) => setData('repeats', e.target.value)}
                >
                    <option value="">Does not repeat</option>
                    {repeatOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.repeats} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="priority" value="Priority" />
                <select
                    id="priority"
                    className={selectClasses}
                    value={data.priority}
                    onChange={(e) => setData('priority', e.target.value)}
                >
                    {priorities.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>

            <div>
                <InputLabel htmlFor="amount" value="Amount payable / cost" />
                <TextInput
                    id="amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    className="mt-1 block w-full"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                />
                <InputError message={errors.amount} className="mt-2" />
            </div>

            {linkOptions && (
                <TaskLinkPicker
                    data={data}
                    setData={setData}
                    errors={errors}
                    linkTypes={linkTypes}
                    linkOptions={linkOptions}
                />
            )}

            <div className="sm:col-span-2">
                <InputLabel htmlFor="notes" value="Notes" />
                <textarea
                    id="notes"
                    rows={3}
                    className={selectClasses}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
                <InputError message={errors.notes} className="mt-2" />
            </div>
        </div>
    );
}
