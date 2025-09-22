import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import DangerButton from '@/Components/DangerButton';
import CancelButton from '@/Components/CancelButton';
import { PlusIcon, TrashIcon, XMarkIcon, DocumentArrowDownIcon, Squares2X2Icon, ListBulletIcon } from '@heroicons/react/24/outline';

export default function Index({ receipts }) {
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [receiptToDelete, setReceiptToDelete] = useState(null);
    const [viewMode, setViewMode] = useState('list'); // 'list' or 'thumbnail'

    const { delete: destroy, processing } = useForm();

    const getStatusBadge = (status) => {
        const badges = {
            pending: 'bg-yellow-100 text-yellow-800',
            processed: 'bg-green-100 text-green-800',
            failed: 'bg-red-100 text-red-800',
        };

        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badges[status]}`}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        );
    };

    const handleDeleteClick = (receipt) => {
        setReceiptToDelete(receipt);
        setShowDeleteModal(true);
    };

    const handleDeleteConfirm = () => {
        if (receiptToDelete) {
            destroy(route('receipts.destroy', receiptToDelete.id), {
                onSuccess: () => {
                    setShowDeleteModal(false);
                    setReceiptToDelete(null);
                },
            });
        }
    };

    const handleDeleteCancel = () => {
        setShowDeleteModal(false);
        setReceiptToDelete(null);
    };

    const formatCurrency = (amount, currency = 'EUR') => {
        if (!amount) return 'N/A';
        return new Intl.NumberFormat('de-DE', {
            style: 'currency',
            currency: currency,
        }).format(amount);
    };

    const formatDate = (date) => {
        return new Date(date).toLocaleDateString('de-DE', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Receipts" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <h2 className="text-2xl font-bold">Receipts</h2>
                                <div className="flex items-center space-x-4">
                                    {/* View Changer Buttons - Hidden on mobile */}
                                    <div className="hidden sm:flex bg-gray-100 rounded-lg p-1">
                                        <button
                                            onClick={() => setViewMode('list')}
                                            className={`flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                                                viewMode === 'list'
                                                    ? 'bg-white text-gray-900 shadow-sm'
                                                    : 'text-gray-500 hover:text-gray-700'
                                            }`}
                                        >
                                            <ListBulletIcon className="h-4 w-4 mr-2" />
                                            List
                                        </button>
                                        <button
                                            onClick={() => setViewMode('thumbnail')}
                                            className={`flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                                                viewMode === 'thumbnail'
                                                    ? 'bg-white text-gray-900 shadow-sm'
                                                    : 'text-gray-500 hover:text-gray-700'
                                            }`}
                                        >
                                            <Squares2X2Icon className="h-4 w-4 mr-2" />
                                            Thumbnail
                                        </button>
                                    </div>
                                    <Link href={route('receipts.create')}>
                                        <PrimaryButton icon={PlusIcon} iconOnly>Upload New Receipt</PrimaryButton>
                                    </Link>
                                </div>
                            </div>

                            {receipts.data.length === 0 ? (
                                <div className="text-center py-12">
                                    <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">No receipts</h3>
                                    <p className="mt-1 text-sm text-gray-500">Get started by uploading a new receipt.</p>
                                    <div className="mt-6">
                                        <Link href={route('receipts.create')}>
                                            <PrimaryButton icon={PlusIcon} iconOnly>Upload Receipt</PrimaryButton>
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    {/* Mobile Thumbnail View - Always show on mobile */}
                                    <div className="block sm:hidden space-y-3">
                                        {receipts.data.map((receipt) => (
                                            <div key={receipt.id} className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                                                <div className="flex items-center p-3">
                                                    {/* Receipt Image - 75x75 */}
                                                    <div 
                                                        className="w-[75px] h-[75px] rounded-lg bg-gray-100 bg-cover bg-center flex-shrink-0"
                                                        style={{
                                                            backgroundImage: receipt.mime?.startsWith('image/') 
                                                                ? `url(${receipt.public_file_url || receipt.file_url})`
                                                                : 'none'
                                                        }}
                                                    >
                                                        {!receipt.mime?.startsWith('image/') && (
                                                            <div className="w-full h-full flex items-center justify-center">
                                                                <svg className="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                </svg>
                                                            </div>
                                                        )}
                                                    </div>
                                                    
                                                    {/* Receipt Info */}
                                                    <div className="ml-3 flex-1 min-w-0">
                                                        <div className="flex items-center justify-between mb-1">
                                                            <h3 className="text-sm font-medium text-gray-900 truncate">
                                                                {receipt.vendor || receipt.original_filename}
                                                            </h3>
                                                            {getStatusBadge(receipt.status)}
                                                        </div>
                                                        
                                                        {receipt.total_amount && (
                                                            <p className="text-lg font-semibold text-gray-900 mb-1">
                                                                {formatCurrency(receipt.total_amount, receipt.currency)}
                                                            </p>
                                                        )}
                                                        
                                                        <p className="text-xs text-gray-500 mb-2">
                                                            {formatDate(receipt.receipt_date || receipt.created_at)}
                                                        </p>
                                                        
                                                        {/* Actions */}
                                                        <div className="flex space-x-2">
                                                            <Link
                                                                href={route('receipts.show', receipt.id)}
                                                                className="flex-1 text-center px-2 py-1 text-xs font-medium text-indigo-600 bg-indigo-50 rounded hover:bg-indigo-100 transition-colors"
                                                            >
                                                                View
                                                            </Link>
                                                            {receipt.mime === 'application/pdf' && (
                                                                <a
                                                                    href={receipt.public_file_url || receipt.file_url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="px-2 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition-colors"
                                                                    title="Open PDF in new tab"
                                                                >
                                                                    <DocumentArrowDownIcon className="h-3 w-3" />
                                                                </a>
                                                            )}
                                                            <button
                                                                onClick={() => handleDeleteClick(receipt)}
                                                                className="px-2 py-1 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 transition-colors"
                                                            >
                                                                <TrashIcon className="h-3 w-3" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    {/* Desktop Views */}
                                    <div className="hidden sm:block">
                                        {viewMode === 'list' ? (
                                        <div className="hidden sm:block overflow-x-auto">
                                            <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Receipt
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Vendor
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Amount
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Status
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Date
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {receipts.data.map((receipt) => (
                                                <tr key={receipt.id} className="hover:bg-gray-50">
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <div className="flex items-center">
                                                            <div className="flex-shrink-0 h-10 w-10">
                                                                {receipt.mime?.startsWith('image/') ? (
                                                                    <img
                                                                        className="h-10 w-10 rounded-lg object-cover"
                                                                        src={receipt.public_file_url || receipt.file_url}
                                                                        alt="Receipt"
                                                                    />
                                                                ) : (
                                                                    <div className="h-10 w-10 rounded-lg bg-gray-200 flex items-center justify-center">
                                                                        <svg className="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                        </svg>
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <div className="ml-4">
                                                                <div className="text-sm font-medium text-gray-900">
                                                                    {receipt.original_filename}
                                                                </div>
                                                                <div className="text-sm text-gray-500">
                                                                    {(receipt.file_size / 1024 / 1024).toFixed(2)} MB
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {receipt.vendor || 'N/A'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {formatCurrency(receipt.total_amount, receipt.currency)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {getStatusBadge(receipt.status)}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <div>
                                                            Receipt: {new Date(receipt.receipt_date).toLocaleDateString('de-DE')} {new Date(receipt.receipt_date).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })}
                                                            {receipt.receipt_date && (
                                                                <div className="text-xs text-gray-400 mt-1">
                                                                    Uploaded: {formatDate(receipt.created_at)}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div className="flex space-x-2">
                                                            <Link
                                                                href={route('receipts.show', receipt.id)}
                                                                className="text-indigo-600 hover:text-indigo-900"
                                                            >
                                                                View
                                                            </Link>
                                                            <button
                                                                onClick={() => handleDeleteClick(receipt)}
                                                                className="text-red-600 hover:text-red-900"
                                                            >
                                                                Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>

                                    {/* Pagination */}
                                    {receipts.links && (
                                        <div className="px-6 py-3 border-t border-gray-200">
                                            <nav className="flex items-center justify-between">
                                                <div className="flex-1 flex justify-between sm:hidden">
                                                    {receipts.links.prev && (
                                                        <Link
                                                            href={receipts.links.prev}
                                                            className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                                        >
                                                            Previous
                                                        </Link>
                                                    )}
                                                    {receipts.links.next && (
                                                        <Link
                                                            href={receipts.links.next}
                                                            className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                                        >
                                                            Next
                                                        </Link>
                                                    )}
                                                </div>
                                                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                                    <div>
                                                        <p className="text-sm text-gray-700">
                                                            Showing <span className="font-medium">{receipts.from}</span> to{' '}
                                                            <span className="font-medium">{receipts.to}</span> of{' '}
                                                            <span className="font-medium">{receipts.total}</span> results
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                                            {receipts.links.map((link, index) => (
                                                                <Link
                                                                    key={index}
                                                                    href={link.url || '#'}
                                                                    className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                                                                        link.active
                                                                            ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                                                                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                                                    } ${index === 0 ? 'rounded-l-md' : ''} ${
                                                                        index === receipts.links.length - 1 ? 'rounded-r-md' : ''
                                                                    }`}
                                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                                />
                                                            ))}
                                                        </nav>
                                                    </div>
                                                </div>
                                            </nav>
                                        </div>
                                    )}
                                        </div>
                                    ) : (
                                        /* Desktop Thumbnail View */
                                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                                            {receipts.data.map((receipt) => (
                                                <div key={receipt.id} className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                                                    {/* Receipt Image */}
                                                    <div className="aspect-square bg-gray-100">
                                                        {receipt.mime?.startsWith('image/') ? (
                                                            <img
                                                                className="w-full h-full object-cover"
                                                                src={receipt.public_file_url || receipt.file_url}
                                                                alt="Receipt"
                                                            />
                                                        ) : (
                                                            <div className="w-full h-full flex items-center justify-center">
                                                                <svg className="h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                </svg>
                                                            </div>
                                                        )}
                                                    </div>
                                                    
                                                    {/* Receipt Info */}
                                                    <div className="p-4">
                                                        <div className="flex items-center justify-between mb-2">
                                                            <h3 className="text-sm font-medium text-gray-900 truncate">
                                                                {receipt.vendor || receipt.original_filename}
                                                            </h3>
                                                            {getStatusBadge(receipt.status)}
                                                        </div>
                                                        
                                                        {receipt.total_amount && (
                                                            <p className="text-lg font-semibold text-gray-900 mb-2">
                                                                {formatCurrency(receipt.total_amount, receipt.currency)}
                                                            </p>
                                                        )}
                                                        
                                                        <p className="text-xs text-gray-500 mb-3">
                                                            {formatDate(receipt.receipt_date || receipt.created_at)}
                                                        </p>
                                                        
                                                        {/* Actions */}
                                                        <div className="flex space-x-2">
                                                            <Link
                                                                href={route('receipts.show', receipt.id)}
                                                                className="flex-1 text-center px-3 py-2 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-md hover:bg-indigo-100 transition-colors"
                                                            >
                                                                View
                                                            </Link>
                                                            {receipt.mime === 'application/pdf' && (
                                                                <a
                                                                    href={receipt.public_file_url || receipt.file_url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    className="px-3 py-2 text-xs font-medium text-blue-600 bg-blue-50 rounded-md hover:bg-blue-100 transition-colors"
                                                                    title="Open PDF in new tab"
                                                                >
                                                                    <DocumentArrowDownIcon className="h-4 w-4" />
                                                                </a>
                                                            )}
                                                            <button
                                                                onClick={() => handleDeleteClick(receipt)}
                                                                className="px-3 py-2 text-xs font-medium text-red-600 bg-red-50 rounded-md hover:bg-red-100 transition-colors"
                                                            >
                                                                <TrashIcon className="h-4 w-4" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Delete Confirmation Modal */}
            {showDeleteModal && (
                <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div className="mt-3 text-center">
                            <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                                <svg className="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-medium text-gray-900 mt-4">Delete Receipt</h3>
                            <div className="mt-2 px-7 py-3">
                                <p className="text-sm text-gray-500">
                                    Are you sure you want to delete "{receiptToDelete?.original_filename}"?
                                    This action cannot be undone and will permanently remove the receipt and its associated data.
                                </p>
                            </div>
                            <div className="items-center px-4 py-3">
                                <div className="flex justify-center space-x-3">
                                    <CancelButton icon={XMarkIcon} iconOnly onClick={handleDeleteCancel} />
                                    <DangerButton
                                        icon={TrashIcon}
                                        iconOnly
                                        onClick={handleDeleteConfirm}
                                        disabled={processing}
                                        className="px-4 py-2 text-sm"
                                    >
                                        {processing ? 'Deleting...' : 'Delete'}
                                    </DangerButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
