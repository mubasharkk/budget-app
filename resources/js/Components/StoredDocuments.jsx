import { useRef } from 'react';
import { router, useForm } from '@inertiajs/react';
import { DocumentIcon, PaperClipIcon, TrashIcon } from '@heroicons/react/24/outline';
import InputError from '@/Components/InputError';

const formatSize = (bytes) =>
    bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;

/**
 * A list of stored files with upload and remove: a receipt's supporting documents,
 * a contract's invoices. The files are only kept, never counted as spending.
 */
export default function StoredDocuments({ title, description, documents, max, accept, uploadUrl, removeUrl, buttonLabel = 'Attach' }) {
    const input = useRef(null);
    const { post, transform, processing, errors, progress, clearErrors } = useForm({ files: [] });

    const upload = (e) => {
        const files = Array.from(e.target.files ?? []);
        if (files.length === 0) {
            return;
        }

        clearErrors();
        transform(() => ({ files }));
        post(uploadUrl, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                if (input.current) {
                    input.current.value = '';
                }
            },
        });
    };

    const remove = (file) => router.delete(removeUrl(file), { preserveScroll: true });

    const full = documents.length >= max;

    return (
        <div>
            <div className="flex items-center justify-between gap-2">
                <h3 className="text-lg font-medium">{title}</h3>
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    disabled={processing || full}
                    className="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                >
                    <PaperClipIcon className="h-4 w-4" />
                    {processing ? `Uploading${progress ? ` ${progress.percentage}%` : ''}…` : buttonLabel}
                </button>
                <input ref={input} type="file" multiple accept={accept} onChange={upload} className="hidden" />
            </div>
            <p className="mt-1 text-sm text-gray-500">{description}</p>

            {documents.length > 0 && (
                <ul className="mt-3 divide-y divide-gray-100 rounded-lg border">
                    {documents.map((file) => (
                        <li key={file.id} className="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                            <a
                                href={file.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex min-w-0 items-center gap-2 text-blue-600 hover:text-blue-800"
                            >
                                <DocumentIcon className="h-4 w-4 flex-shrink-0" />
                                <span className="truncate">{file.name}</span>
                                <span className="flex-shrink-0 text-xs text-gray-400">{formatSize(file.size)}</span>
                            </a>
                            <button
                                type="button"
                                onClick={() => remove(file)}
                                className="flex-shrink-0 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                title="Remove"
                            >
                                <TrashIcon className="h-4 w-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <InputError message={errors.files ?? Object.entries(errors).find(([key]) => key.startsWith('files.'))?.[1]} className="mt-2" />
        </div>
    );
}
