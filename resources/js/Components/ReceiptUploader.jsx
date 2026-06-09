import { useCallback, useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    CameraIcon,
    PhotoIcon,
    DocumentIcon,
    ClipboardDocumentIcon,
    ArrowUpTrayIcon,
} from '@heroicons/react/24/outline';
import InputError from '@/Components/InputError';

const ACCEPTED_TYPES = [
    'image/jpeg',
    'image/png',
    'image/heic',
    'image/heif',
    'image/webp',
    'application/pdf',
];

const MAX_FILES = 5;
const MAX_BYTES = 15 * 1024 * 1024;

function isAcceptedFile(file) {
    if (ACCEPTED_TYPES.includes(file.type)) {
        return true;
    }

    const name = file.name?.toLowerCase() ?? '';

    return (
        name.endsWith('.heic') ||
        name.endsWith('.heif') ||
        name.endsWith('.jpg') ||
        name.endsWith('.jpeg') ||
        name.endsWith('.png') ||
        name.endsWith('.webp') ||
        name.endsWith('.pdf')
    );
}

function ActionButton({ icon: Icon, label, sublabel, onClick, disabled }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 p-6 text-center transition hover:border-indigo-300 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <Icon className="h-8 w-8 text-indigo-600" />
            <span className="text-sm font-semibold text-gray-900">{label}</span>
            {sublabel && (
                <span className="text-xs text-gray-500">{sublabel}</span>
            )}
        </button>
    );
}

export default function ReceiptUploader({
    mode = 'batch',
    redirectTo = route('receipts.index'),
    onUploaded,
    className = '',
}) {
    const cameraRef = useRef(null);
    const galleryRef = useRef(null);
    const pdfRef = useRef(null);
    const dropRef = useRef(null);

    const [files, setFiles] = useState([]);
    const [previews, setPreviews] = useState([]);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState(null);
    const [successMessage, setSuccessMessage] = useState(null);

    const uploadFiles = useCallback((fileList) => {
        if (fileList.length === 0) {
            setError('Please select at least one receipt.');
            return;
        }

        setUploading(true);
        setError(null);
        setSuccessMessage(null);

        const formData = new FormData();
        fileList.forEach((file) => formData.append('files[]', file));

        router.post(route('receipts.store'), formData, {
            forceFormData: true,
            preserveScroll: mode === 'instant',
            onSuccess: () => {
                setFiles([]);
                setPreviews([]);
                setSuccessMessage(
                    fileList.length === 1
                        ? 'Receipt uploaded — processing started.'
                        : `${fileList.length} receipts uploaded — processing started.`,
                );
                onUploaded?.(fileList.length);
            },
            onError: (errors) => {
                const first =
                    errors.files ||
                    errors['files.0'] ||
                    Object.values(errors)[0];
                setError(
                    typeof first === 'string'
                        ? first
                        : 'Upload failed. Please try again.',
                );
            },
            onFinish: () => setUploading(false),
        });
    }, [mode, onUploaded]);

    const addFiles = useCallback(
        (incoming) => {
            const accepted = Array.from(incoming).filter(isAcceptedFile);
            if (accepted.length === 0) {
                setError('Please choose a photo, screenshot, or PDF receipt.');
                return;
            }

            const oversized = accepted.find((f) => f.size > MAX_BYTES);
            if (oversized) {
                setError('Each file must be smaller than 15 MB.');
                return;
            }

            setError(null);

            if (mode === 'instant') {
                uploadFiles(accepted);
                return;
            }

            setFiles((prev) => {
                const merged = [...prev, ...accepted].slice(0, MAX_FILES);
                if (prev.length + accepted.length > MAX_FILES) {
                    setError(`You can upload up to ${MAX_FILES} files at once.`);
                }
                return merged;
            });

            accepted.forEach((file) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        setPreviews((prev) => [...prev, e.target.result]);
                    };
                    reader.readAsDataURL(file);
                } else {
                    setPreviews((prev) => [...prev, null]);
                }
            });
        },
        [mode, uploadFiles],
    );

    const handlePaste = useCallback(
        (event) => {
            const items = event.clipboardData?.items;
            if (!items) {
                return;
            }

            const pasted = [];
            for (const item of items) {
                if (item.kind === 'file') {
                    const file = item.getAsFile();
                    if (file) {
                        pasted.push(file);
                    }
                }
            }

            if (pasted.length > 0) {
                event.preventDefault();
                addFiles(pasted);
            }
        },
        [addFiles],
    );

    useEffect(() => {
        document.addEventListener('paste', handlePaste);
        return () => document.removeEventListener('paste', handlePaste);
    }, [handlePaste]);

    useEffect(() => {
        const el = dropRef.current;
        if (!el) {
            return undefined;
        }

        const prevent = (e) => {
            e.preventDefault();
            e.stopPropagation();
        };

        const onDrop = (e) => {
            prevent(e);
            if (e.dataTransfer?.files?.length) {
                addFiles(e.dataTransfer.files);
            }
        };

        el.addEventListener('dragenter', prevent);
        el.addEventListener('dragover', prevent);
        el.addEventListener('drop', onDrop);

        return () => {
            el.removeEventListener('dragenter', prevent);
            el.removeEventListener('dragover', prevent);
            el.removeEventListener('drop', onDrop);
        };
    }, [addFiles]);

    const removeFile = (index) => {
        setFiles((prev) => prev.filter((_, i) => i !== index));
        setPreviews((prev) => prev.filter((_, i) => i !== index));
    };

    return (
        <div ref={dropRef} className={`space-y-4 ${className}`}>
            <input
                ref={cameraRef}
                type="file"
                accept="image/*"
                capture="environment"
                className="hidden"
                onChange={(e) => {
                    if (e.target.files?.length) {
                        addFiles(e.target.files);
                    }
                    e.target.value = '';
                }}
            />
            <input
                ref={galleryRef}
                type="file"
                accept="image/*,.heic,.heif,.webp"
                multiple={mode === 'batch'}
                className="hidden"
                onChange={(e) => {
                    if (e.target.files?.length) {
                        addFiles(e.target.files);
                    }
                    e.target.value = '';
                }}
            />
            <input
                ref={pdfRef}
                type="file"
                accept="application/pdf,.pdf"
                multiple={mode === 'batch'}
                className="hidden"
                onChange={(e) => {
                    if (e.target.files?.length) {
                        addFiles(e.target.files);
                    }
                    e.target.value = '';
                }}
            />

            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <ActionButton
                    icon={CameraIcon}
                    label="Take photo"
                    sublabel="Opens camera"
                    onClick={() => cameraRef.current?.click()}
                    disabled={uploading}
                />
                <ActionButton
                    icon={PhotoIcon}
                    label="Photo library"
                    sublabel="Images & screenshots"
                    onClick={() => galleryRef.current?.click()}
                    disabled={uploading}
                />
                <ActionButton
                    icon={DocumentIcon}
                    label="PDF"
                    sublabel="Digital receipts"
                    onClick={() => pdfRef.current?.click()}
                    disabled={uploading}
                />
                <ActionButton
                    icon={ClipboardDocumentIcon}
                    label="Paste"
                    sublabel="Ctrl/Cmd+V screenshot"
                    onClick={() =>
                        setError(
                            'Paste a screenshot with Ctrl+V (or Cmd+V on Mac).',
                        )
                    }
                    disabled={uploading}
                />
            </div>

            {mode === 'batch' && (
                <p className="text-center text-xs text-gray-500">
                    Or drag and drop files here · up to {MAX_FILES} files · 15
                    MB each
                </p>
            )}

            {uploading && (
                <div className="rounded-lg bg-indigo-50 px-4 py-3 text-center text-sm font-medium text-indigo-700">
                    Uploading…
                </div>
            )}

            {successMessage && (
                <div className="rounded-lg bg-green-50 px-4 py-3 text-center text-sm font-medium text-green-700">
                    {successMessage}
                </div>
            )}

            <InputError message={error} />

            {mode === 'batch' && files.length > 0 && (
                <div className="space-y-3">
                    <div className="text-sm text-gray-600">
                        {files.length} of {MAX_FILES} selected
                    </div>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        {files.map((file, index) => (
                            <div
                                key={`${file.name}-${index}`}
                                className="relative rounded-lg border bg-gray-50 p-2"
                            >
                                <button
                                    type="button"
                                    onClick={() => removeFile(index)}
                                    className="absolute right-1 top-1 rounded bg-white px-1.5 text-xs text-red-600 shadow"
                                >
                                    ✕
                                </button>
                                {previews[index] ? (
                                    <img
                                        src={previews[index]}
                                        alt={file.name}
                                        className="mb-2 h-24 w-full rounded object-cover"
                                    />
                                ) : (
                                    <div className="mb-2 flex h-24 items-center justify-center rounded bg-gray-200">
                                        <DocumentIcon className="h-8 w-8 text-gray-500" />
                                    </div>
                                )}
                                <div className="truncate text-xs text-gray-600">
                                    {file.name}
                                </div>
                            </div>
                        ))}
                    </div>

                    <button
                        type="button"
                        onClick={() => uploadFiles(files)}
                        disabled={uploading}
                        className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 sm:w-auto"
                    >
                        <ArrowUpTrayIcon className="h-5 w-5" />
                        Upload {files.length}{' '}
                        {files.length === 1 ? 'receipt' : 'receipts'}
                    </button>
                </div>
            )}

            {mode === 'instant' && !uploading && (
                <p className="text-center text-sm text-gray-500">
                    Photos upload immediately after capture.{' '}
                    <a
                        href={redirectTo}
                        className="font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        View receipts
                    </a>
                </p>
            )}
        </div>
    );
}
