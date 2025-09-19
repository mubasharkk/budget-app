import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import CancelButton from '@/Components/CancelButton';
import InputError from '@/Components/InputError';

export default function Create() {
    const [filePreview, setFilePreview] = useState(null);
    const [isUploading, setIsUploading] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        file: null,
    });

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setData('file', file);
            
            // Create preview for images
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => setFilePreview(e.target.result);
                reader.readAsDataURL(file);
            } else {
                setFilePreview(null);
            }
        }
    };

    const submit = (e) => {
        e.preventDefault();
        setIsUploading(true);
        
        post(route('receipts.store'), {
            onFinish: () => {
                setIsUploading(false);
                reset('file');
                setFilePreview(null);
            },
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Upload Receipt" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h2 className="text-2xl font-bold mb-6">Upload Receipt</h2>
                            
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Select Receipt File
                                    </label>
                                    
                                    <div className="space-y-4">
                                        {/* Camera capture for mobile */}
                                        <div>
                                            <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                                <div className="flex flex-col items-center justify-center pt-5 pb-6">
                                                    <svg className="w-8 h-8 mb-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                                        <path stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 6.5 0 0 0 5.5 6.5a5.56 5.56 0 0 0-1.975 3.025A3 3 0 0 0 3 13h3m0-3.5v3.5m0-3.5h3.5m-3.5 0h3.5"/>
                                                    </svg>
                                                    <p className="mb-2 text-sm text-gray-500">
                                                        <span className="font-semibold">Scan with Camera</span>
                                                    </p>
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        capture="environment"
                                                        onChange={handleFileChange}
                                                        className="hidden"
                                                    />
                                                </div>
                                            </label>
                                        </div>

                                        {/* File upload */}
                                        <div>
                                            <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                                <div className="flex flex-col items-center justify-center pt-5 pb-6">
                                                    <svg className="w-8 h-8 mb-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                                        <path stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 6.5 0 0 0 5.5 6.5a5.56 5.56 0 0 0-1.975 3.025A3 3 0 0 0 3 13h3m0-3.5v3.5m0-3.5h3.5m-3.5 0h3.5"/>
                                                    </svg>
                                                    <p className="mb-2 text-sm text-gray-500">
                                                        <span className="font-semibold">Upload Photo/PDF</span>
                                                    </p>
                                                    <input
                                                        type="file"
                                                        accept=".jpg,.jpeg,.png,.heic,.webp,.pdf"
                                                        onChange={handleFileChange}
                                                        className="hidden"
                                                    />
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <InputError message={errors.file} className="mt-2" />
                                </div>

                                {/* File Preview */}
                                {filePreview && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Preview
                                        </label>
                                        <div className="border rounded-lg p-4">
                                            <img
                                                src={filePreview}
                                                alt="File preview"
                                                className="max-w-full h-48 object-contain mx-auto"
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* File Info */}
                                {data.file && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Selected File
                                        </label>
                                        <div className="bg-gray-50 p-3 rounded-lg">
                                            <p className="text-sm text-gray-600">
                                                <strong>Name:</strong> {data.file.name}
                                            </p>
                                            <p className="text-sm text-gray-600">
                                                <strong>Size:</strong> {(data.file.size / 1024 / 1024).toFixed(2)} MB
                                            </p>
                                            <p className="text-sm text-gray-600">
                                                <strong>Type:</strong> {data.file.type}
                                            </p>
                                        </div>
                                    </div>
                                )}

                                <div className="flex items-center justify-end space-x-4">
                                    <CancelButton 
                                        variant="link"
                                        href={route('receipts.index')}
                                    />
                                    <PrimaryButton 
                                        disabled={processing || !data.file || isUploading}
                                        className="min-w-32"
                                    >
                                        {isUploading ? 'Uploading...' : 'Upload Receipt'}
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}