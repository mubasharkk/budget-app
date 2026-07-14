import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { PaperClipIcon } from '@heroicons/react/24/outline';

const selectClasses =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

const formatBytes = (bytes) => {
    if (!bytes) return '';
    const kb = bytes / 1024;
    return kb < 1024
        ? `${Math.round(kb)} KB`
        : `${(kb / 1024).toFixed(1)} MB`;
};

export default function ExpenseForm({
    data,
    setData,
    errors,
    expenseTypes = [],
    currencies = [],
    document = null,
}) {
    const existingVisible = document && !data.remove_document && !data.document;
    return (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <InputLabel htmlFor="amount" value="Amount" />
                <TextInput
                    id="amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    className="mt-1 block w-full"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    isFocused
                />
                <InputError message={errors.amount} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="currency" value="Currency" />
                <select
                    id="currency"
                    className={selectClasses}
                    value={data.currency}
                    onChange={(e) => setData('currency', e.target.value)}
                >
                    {currencies.map((c) => (
                        <option key={c} value={c}>
                            {c}
                        </option>
                    ))}
                </select>
                <InputError message={errors.currency} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="spent_on" value="Spent on" />
                <TextInput
                    id="spent_on"
                    type="date"
                    className="mt-1 block w-full"
                    value={data.spent_on}
                    onChange={(e) => setData('spent_on', e.target.value)}
                />
                <InputError message={errors.spent_on} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="expense_type" value="Type" />
                <select
                    id="expense_type"
                    className={selectClasses}
                    value={data.expense_type}
                    onChange={(e) => setData('expense_type', e.target.value)}
                >
                    {expenseTypes.map((t) => (
                        <option key={t.value} value={t.value}>
                            {t.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.expense_type} className="mt-2" />
            </div>

            <div className="sm:col-span-2">
                <InputLabel htmlFor="description" value="Description" />
                <TextInput
                    id="description"
                    type="text"
                    className="mt-1 block w-full"
                    value={data.description ?? ''}
                    onChange={(e) => setData('description', e.target.value)}
                    placeholder="e.g. Client lunch, office supplies, repair"
                />
                <InputError message={errors.description} className="mt-2" />
            </div>

            <div className="sm:col-span-2">
                <InputLabel htmlFor="notes" value="Notes" />
                <textarea
                    id="notes"
                    rows={3}
                    className={selectClasses}
                    value={data.notes ?? ''}
                    onChange={(e) => setData('notes', e.target.value)}
                />
                <InputError message={errors.notes} className="mt-2" />
            </div>

            <div className="sm:col-span-2">
                <InputLabel
                    htmlFor="document"
                    value="Invoice / document (optional)"
                />

                {existingVisible && (
                    <div className="mt-1 flex items-center justify-between rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                        <a
                            href={document.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex min-w-0 items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            <PaperClipIcon className="h-4 w-4 shrink-0" />
                            <span className="truncate">{document.name}</span>
                            <span className="shrink-0 text-xs text-gray-400">
                                {formatBytes(document.size)}
                            </span>
                        </a>
                        <button
                            type="button"
                            onClick={() => setData('remove_document', true)}
                            className="ms-3 shrink-0 text-sm font-medium text-red-600 hover:text-red-800"
                        >
                            Remove
                        </button>
                    </div>
                )}

                {document && data.remove_document && !data.document && (
                    <div className="mt-1 flex items-center gap-2 text-sm text-gray-500">
                        <span>Document will be removed when you save.</span>
                        <button
                            type="button"
                            onClick={() => setData('remove_document', false)}
                            className="font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            Undo
                        </button>
                    </div>
                )}

                <input
                    id="document"
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.heif"
                    className="mt-2 block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                    onChange={(e) =>
                        setData('document', e.target.files?.[0] ?? null)
                    }
                />
                <p className="mt-1 text-xs text-gray-400">
                    PDF or image (JPG, PNG, WebP, HEIC), up to 15 MB.
                    {document && ' Uploading a new file replaces the current one.'}
                </p>
                <InputError message={errors.document} className="mt-2" />
            </div>
        </div>
    );
}
