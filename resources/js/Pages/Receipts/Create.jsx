import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ReceiptUploader from '@/Components/ReceiptUploader';
import ExpenseTypeToggle from '@/Components/ExpenseTypeToggle';
import { CameraIcon } from '@heroicons/react/24/outline';

const KIND_OPTIONS = [
    { value: 'expense', label: 'Expense' },
    { value: 'income', label: 'Income' },
];

export default function Create() {
    const [expenseType, setExpenseType] = useState('personal');
    const [kind, setKind] = useState('expense');

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
                        <div className="space-y-5 p-6">
                            <div className="flex flex-wrap gap-6">
                                <div>
                                    <div className="mb-2 text-sm font-medium text-gray-700">
                                        These receipts are
                                    </div>
                                    <ExpenseTypeToggle
                                        value={kind}
                                        onChange={setKind}
                                        options={KIND_OPTIONS}
                                    />
                                </div>
                                {kind === 'expense' && (
                                    <div>
                                        <div className="mb-2 text-sm font-medium text-gray-700">
                                            Charge these receipts to
                                        </div>
                                        <ExpenseTypeToggle
                                            value={expenseType}
                                            onChange={setExpenseType}
                                        />
                                    </div>
                                )}
                            </div>
                            {kind === 'income' && (
                                <p className="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">
                                    Upload payslips, payout confirmations or
                                    refunds. The amount received is added to
                                    your one-time income and is not counted
                                    as spending.
                                </p>
                            )}
                            <ReceiptUploader
                                mode="batch"
                                expenseType={expenseType}
                                kind={kind}
                            />
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
