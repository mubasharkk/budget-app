import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ReceiptUploader from '@/Components/ReceiptUploader';
import { CameraIcon } from '@heroicons/react/24/outline';

export default function Create() {
    return (
        <AuthenticatedLayout>
            <Head title="Upload Receipt" />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-2xl font-bold text-gray-900">
                            Upload receipts
                        </h2>
                        <Link
                            href={route('receipts.scan')}
                            className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 sm:hidden"
                        >
                            <CameraIcon className="h-5 w-5" />
                            Quick scan
                        </Link>
                    </div>

                    <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                        <div className="border-b border-gray-100 px-6 py-4">
                            <p className="text-sm text-gray-600">
                                Photos, screenshots, and PDFs are supported.
                                Images are converted automatically for OCR.
                            </p>
                        </div>
                        <div className="p-6">
                            <ReceiptUploader mode="batch" />
                        </div>
                    </div>

                    <p className="mt-4 text-center text-sm text-gray-500">
                        On mobile?{' '}
                        <Link
                            href={route('receipts.scan')}
                            className="font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            Use quick scan
                        </Link>{' '}
                        for one-tap camera upload.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
