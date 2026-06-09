import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ReceiptUploader from '@/Components/ReceiptUploader';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';

export default function Scan() {
    return (
        <AuthenticatedLayout>
            <Head title="Scan Receipt" />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-lg px-4 sm:px-6">
                    <Link
                        href={route('receipts.index')}
                        className="mb-4 inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900"
                    >
                        <ArrowLeftIcon className="h-4 w-4" />
                        Back to receipts
                    </Link>

                    <div className="overflow-hidden rounded-2xl bg-white shadow-sm">
                        <div className="bg-indigo-600 px-6 py-8 text-center text-white">
                            <h1 className="text-2xl font-bold">Scan receipt</h1>
                            <p className="mt-2 text-sm text-indigo-100">
                                Take a photo, pick from gallery, paste a
                                screenshot, or upload a PDF — processing starts
                                instantly.
                            </p>
                        </div>

                        <div className="p-6">
                            <ReceiptUploader mode="instant" />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
