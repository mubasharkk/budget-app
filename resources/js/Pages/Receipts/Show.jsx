import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import DangerButton from '@/Components/DangerButton';
import CancelButton from '@/Components/CancelButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

export default function Show({ receipt }) {
    const [categories, setCategories] = useState([]);
    const [subcategories, setSubcategories] = useState([]);
    const [showOcrText, setShowOcrText] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [showRetryModal, setShowRetryModal] = useState(false);

    const { data, setData, patch, processing, errors, reset } = useForm({
        vendor: receipt.vendor || '',
        currency: receipt.currency || 'EUR',
        total_amount: receipt.total_amount || '',
        items: receipt.items || [],
    });

    const { delete: destroy, processing: deleteProcessing } = useForm();
    const { post: retryProcessing, processing: retryProcessingLoading } = useForm();

    useEffect(() => {
        // Fetch categories
        fetch('/categories')
            .then(response => response.json())
            .then(data => {
                setCategories(data);
            });
    }, []);

    const handleItemCategoryChange = (itemIndex, categoryId) => {
        const newItems = [...data.items];
        newItems[itemIndex] = {
            ...newItems[itemIndex],
            category_id: categoryId,
            subcategory_id: '' // Reset subcategory when category changes
        };
        setData('items', newItems);
    };

    const handleItemSubcategoryChange = (itemIndex, subcategoryId) => {
        const newItems = [...data.items];
        newItems[itemIndex] = {
            ...newItems[itemIndex],
            subcategory_id: subcategoryId
        };
        setData('items', newItems);
    };

    const getSubcategoriesForItem = (itemIndex) => {
        const item = data.items[itemIndex];
        if (!item?.category_id) return [];

        const selectedCategory = categories.find(cat => cat.id === parseInt(item.category_id));
        return selectedCategory?.subcategories || [];
    };

    const handleItemChange = (index, field, value) => {
        const newItems = [...data.items];
        newItems[index] = { ...newItems[index], [field]: value };

        // Auto-calculate total if quantity or unit_price changes
        if (field === 'quantity' || field === 'unit_price') {
            const quantity = parseFloat(field === 'quantity' ? value : newItems[index].quantity) || 0;
            const unitPrice = parseFloat(field === 'unit_price' ? value : newItems[index].unit_price) || 0;
            newItems[index].total = (quantity * unitPrice).toFixed(2);
        }

        setData('items', newItems);
    };

    const addItem = () => {
        setData('items', [...data.items, {
            name: '',
            quantity: 1,
            unit_price: 0,
            total: 0,
            category_id: '',
            subcategory_id: ''
        }]);
    };

    const removeItem = (index) => {
        const newItems = data.items.filter((_, i) => i !== index);
        setData('items', newItems);
    };

    const submit = (e) => {
        e.preventDefault();
        patch(route('receipts.update', receipt.id));
    };

    const handleRetryClick = () => {
        setShowRetryModal(true);
    };

    const handleRetryConfirm = () => {
        retryProcessing(route('receipts.retry', receipt.id), {
            onSuccess: () => {
                setShowRetryModal(false);
            },
        });
    };

    const handleRetryCancel = () => {
        setShowRetryModal(false);
    };

    const handleDeleteClick = () => {
        setShowDeleteModal(true);
    };

    const handleDeleteConfirm = () => {
        destroy(route('receipts.destroy', receipt.id));
    };

    const handleDeleteCancel = () => {
        setShowDeleteModal(false);
    };

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

    const formatCurrency = (amount, currency = 'EUR') => {
        if (!amount) return 'N/A';
        return new Intl.NumberFormat('de-DE', {
            style: 'currency',
            currency: currency,
        }).format(amount);
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Receipt: ${receipt.original_filename}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="flex justify-between items-center mb-6">
                                <div>
                                    <h2 className="text-2xl font-bold">{receipt.original_filename}</h2>
                                    <div className="flex items-center space-x-4 mt-2">
                                        {getStatusBadge(receipt.status)}
                                        <span className="text-sm text-gray-500">
                                            Uploaded {new Date(receipt.created_at).toLocaleDateString('de-DE')}
                                        </span>
                                    </div>
                                </div>
                                <div className="flex space-x-2">
                                    <Link href={route('receipts.index')}>
                                        <PrimaryButton variant="secondary">Back to List</PrimaryButton>
                                    </Link>
                                    {receipt.status === 'failed' && (
                                        <button
                                            onClick={handleRetryClick}
                                            disabled={retryProcessingLoading}
                                            className="uppercase tracking-widest text-xs px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 disabled:opacity-50"
                                        >
                                            {retryProcessingLoading ? 'Retrying...' : 'Retry Processing'}
                                        </button>
                                    )}
                                    <DangerButton onClick={handleDeleteClick}>
                                        Delete Receipt
                                    </DangerButton>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                {/* Left Column - File Preview */}
                                <div>
                                    <h3 className="text-lg font-medium mb-4">Receipt Preview</h3>
                                    <div className="border rounded-lg p-4">
                                        {receipt.mime?.startsWith('image/') ? (
                                            <img
                                                src={receipt.public_file_url || receipt.file_url}
                                                alt="Receipt"
                                                className="max-w-full h-auto rounded-lg"
                                            />
                                        ) : (
                                            <div className="flex flex-col items-center justify-center h-64 bg-gray-100 rounded-lg">
                                                <svg className="h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <p className="mt-2 text-sm text-gray-500">PDF Document</p>
                                            </div>
                                        )}
                                    </div>

                                    {/* OCR Text */}
                                    {receipt.ocr_text && (
                                        <div className="mt-6">
                                            <button
                                                onClick={() => setShowOcrText(!showOcrText)}
                                                className="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900"
                                            >
                                                <svg className={`mr-2 h-4 w-4 transform transition-transform ${showOcrText ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                </svg>
                                                OCR Text (Collapsible)
                                            </button>
                                            {showOcrText && (
                                                <div className="mt-2 p-4 bg-gray-50 rounded-lg">
                                                    <pre className="text-sm text-gray-700 whitespace-pre-wrap">{receipt.ocr_text}</pre>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {/* Error Message */}
                                    {receipt.status === 'failed' && receipt.error_message && (
                                        <div className="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                                            <h4 className="text-sm font-medium text-red-800">Processing Error</h4>
                                            <p className="mt-1 text-sm text-red-700">{receipt.error_message}</p>
                                        </div>
                                    )}
                                </div>

                                {/* Right Column - Form */}
                                <div>
                                    <h3 className="text-lg font-medium mb-4">Receipt Details</h3>

                                    <form onSubmit={submit} className="space-y-6">

                                        {/* Vendor */}
                                        <div>
                                            <InputLabel htmlFor="vendor" value="Vendor" />
                                            <TextInput
                                                id="vendor"
                                                value={data.vendor}
                                                onChange={(e) => setData('vendor', e.target.value)}
                                                className="mt-1 block w-full"
                                            />
                                            <InputError message={errors.vendor} className="mt-2" />
                                        </div>

                                        {/* Currency and Total */}
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <InputLabel htmlFor="currency" value="Currency" />
                                                <TextInput
                                                    id="currency"
                                                    value={data.currency}
                                                    onChange={(e) => setData('currency', e.target.value)}
                                                    className="mt-1 block w-full"
                                                    maxLength="3"
                                                />
                                                <InputError message={errors.currency} className="mt-2" />
                                            </div>
                                            <div>
                                                <InputLabel htmlFor="total_amount" value="Total Amount" />
                                                <TextInput
                                                    id="total_amount"
                                                    type="number"
                                                    step="0.01"
                                                    value={data.total_amount}
                                                    onChange={(e) => setData('total_amount', e.target.value)}
                                                    className="mt-1 block w-full"
                                                />
                                                <InputError message={errors.total_amount} className="mt-2" />
                                            </div>
                                        </div>

                                        {/* Items */}
                                        <div>
                                            <div className="flex justify-between items-center mb-4">
                                                <InputLabel value="Items" />
                                                <button
                                                    type="button"
                                                    onClick={addItem}
                                                    className="text-sm text-indigo-600 hover:text-indigo-900"
                                                >
                                                    + Add Item
                                                </button>
                                            </div>

                                            <div className="space-y-4">
                                                {data.items.map((item, index) => (
                                                    <div key={index} className="border rounded-lg p-4 bg-gray-50">
                                                        <div className="grid grid-cols-12 gap-2 items-end mb-3">
                                                            <div className="col-span-5">
                                                                <TextInput
                                                                    placeholder="Item name"
                                                                    value={item.name}
                                                                    onChange={(e) => handleItemChange(index, 'name', e.target.value)}
                                                                    className="block w-full"
                                                                />
                                                            </div>
                                                            <div className="col-span-2">
                                                                <TextInput
                                                                    type="number"
                                                                    step="0.001"
                                                                    placeholder="Qty"
                                                                    value={item.quantity}
                                                                    onChange={(e) => handleItemChange(index, 'quantity', e.target.value)}
                                                                    className="block w-full"
                                                                />
                                                            </div>
                                                            <div className="col-span-2">
                                                                <TextInput
                                                                    type="number"
                                                                    step="0.0001"
                                                                    placeholder="Unit Price"
                                                                    value={item.unit_price}
                                                                    onChange={(e) => handleItemChange(index, 'unit_price', e.target.value)}
                                                                    className="block w-full"
                                                                />
                                                            </div>
                                                            <div className="col-span-2">
                                                                <TextInput
                                                                    type="number"
                                                                    step="0.01"
                                                                    placeholder="Total"
                                                                    value={item.total}
                                                                    onChange={(e) => handleItemChange(index, 'total', e.target.value)}
                                                                    className="block w-full"
                                                                    readOnly
                                                                />
                                                            </div>
                                                            <div className="col-span-1">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => removeItem(index)}
                                                                    className="text-red-600 hover:text-red-900"
                                                                >
                                                                    ×
                                                                </button>
                                                            </div>
                                                        </div>

                                                        {/* Category Selection for Item */}
                                                        <div className="grid grid-cols-2 gap-3">
                                                            <div>
                                                                <InputLabel htmlFor={`item_${index}_category`} value="Category" />
                                                                <select
                                                                    id={`item_${index}_category`}
                                                                    value={item.category_id || ''}
                                                                    onChange={(e) => handleItemCategoryChange(index, e.target.value)}
                                                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                >
                                                                    <option value="">Select category</option>
                                                                    {categories.map((category) => (
                                                                        <option key={category.id} value={category.id}>
                                                                            {category.name}
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <InputLabel htmlFor={`item_${index}_subcategory`} value="Subcategory" />
                                                                <select
                                                                    id={`item_${index}_subcategory`}
                                                                    value={item.subcategory_id || ''}
                                                                    onChange={(e) => handleItemSubcategoryChange(index, e.target.value)}
                                                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                    disabled={!item.category_id}
                                                                >
                                                                    <option value="">Select subcategory</option>
                                                                    {getSubcategoriesForItem(index).map((subcategory) => (
                                                                        <option key={subcategory.id} value={subcategory.id}>
                                                                            {subcategory.name}
                                                                        </option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-end space-x-4">
                                            <Link
                                                href={route('receipts.index')}
                                                className="text-gray-600 hover:text-gray-900"
                                            >
                                                Cancel
                                            </Link>
                                            <PrimaryButton disabled={processing}>
                                                {processing ? 'Saving...' : 'Save Changes'}
                                            </PrimaryButton>
                                        </div>
                                    </form>
                                </div>
                            </div>
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
                                    Are you sure you want to delete "{receipt.original_filename}"?
                                    This action cannot be undone and will permanently remove the receipt and its associated data.
                                </p>
                            </div>
                            <div className="items-center px-4 py-3">
                                <div className="flex justify-center space-x-3">
                                    <CancelButton onClick={handleDeleteCancel} />
                                    <DangerButton
                                        onClick={handleDeleteConfirm}
                                        disabled={deleteProcessing}
                                        className="px-4 py-2"
                                    >
                                        {deleteProcessing ? 'Deleting...' : 'Delete'}
                                    </DangerButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Retry Confirmation Modal */}
            {showRetryModal && (
                <div className="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div className="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div className="mt-3 text-center">
                            <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100">
                                <svg className="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-medium text-gray-900 mt-4">Retry Processing</h3>
                            <div className="mt-2 px-7 py-3">
                                <p className="text-sm text-gray-500">
                                    Are you sure you want to retry processing "{receipt.original_filename}"?
                                    This will attempt to process the receipt again using OCR and AI parsing.
                                </p>
                            </div>
                            <div className="items-center px-4 py-3">
                                <div className="flex justify-center space-x-3">
                                    <CancelButton onClick={handleRetryCancel} />
                                    <button
                                        onClick={handleRetryConfirm}
                                        disabled={retryProcessingLoading}
                                        className="px-4 py-2 bg-yellow-600 uppercase text-xs text-white font-medium rounded-md shadow-sm hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 disabled:opacity-50"
                                    >
                                        {retryProcessingLoading ? 'Retrying...' : 'Retry Processing'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
