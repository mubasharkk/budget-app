import { useRef } from 'react';
import { router, useForm } from '@inertiajs/react';
import { DocumentIcon, PaperClipIcon, TrashIcon } from '@heroicons/react/24/outline';
import InputError from '@/Components/InputError';

const ACCEPT = '.jpg,.jpeg,.png,.heic,.heif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.eml';

const formatSize = (bytes) =>
    bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;

/**
 * Supporting documents on a receipt (bank payment confirmations, delivery notes …).
 * They are only stored here and never counted as spending.
 */
export default function ReceiptAttachments({ receiptId, attachments, max }) {
    const input = useRef(null);
    const { post, transform, processing, errors, progress, clearErrors } = useForm({ files: [] });

    const upload = (e) => {
        const files = Array.from(e.target.files ?? []);
        if (files.length === 0) {
            return;
        }

        clearErrors();
        transform(() => ({ files }));
        post(route('receipts.attachments.store', receiptId), {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                if (input.current) {
                    input.current.value = '';
                }
            },
        });
    };

    const remove = (attachment) =>
        router.delete(route('receipts.attachments.destroy', [receiptId, attachment.id]), { preserveScroll: true });

    const full = attachments.length >= max;

    return (
        <div className="mt-6">
            <div className="flex items-center justify-between gap-2">
                <h3 className="text-lg font-medium">Additional documents</h3>
                <button
                    type="button"
                    onClick={() => input.current?.click()}
                    disabled={processing || full}
                    className="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                >
                    <PaperClipIcon className="h-4 w-4" />
                    {processing ? `Uploading${progress ? ` ${progress.percentage}%` : ''}…` : 'Attach'}
                </button>
                <input ref={input} type="file" multiple accept={ACCEPT} onChange={upload} className="hidden" />
            </div>
            <p className="mt-1 text-sm text-gray-500">
                Bank payment confirmations, delivery notes and other supporting files. They are not counted as expenses.
            </p>

            {attachments.length > 0 && (
                <ul className="mt-3 divide-y divide-gray-100 rounded-lg border">
                    {attachments.map((attachment) => (
                        <li key={attachment.id} className="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                            <a
                                href={attachment.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex min-w-0 items-center gap-2 text-blue-600 hover:text-blue-800"
                            >
                                <DocumentIcon className="h-4 w-4 flex-shrink-0" />
                                <span className="truncate">{attachment.name}</span>
                                <span className="flex-shrink-0 text-xs text-gray-400">{formatSize(attachment.size)}</span>
                            </a>
                            <button
                                type="button"
                                onClick={() => remove(attachment)}
                                className="flex-shrink-0 rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                title="Remove document"
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
