import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import CancelButton from '@/Components/CancelButton';
import InputError from '@/Components/InputError';
import { XMarkIcon, CheckIcon } from '@heroicons/react/24/outline';

export default function Create() {
    const [filePreviews, setFilePreviews] = useState([]);
    const [isUploading, setIsUploading] = useState(false);
    const [uploadedFiles, setUploadedFiles] = useState([]);

    const { data, setData, post, processing, errors, reset } = useForm({
        files: [],
    });

    const handleFileChange = (e) => {
        const files = Array.from(e.target.files);
        const maxFiles = 5;
        const currentFileCount = uploadedFiles.length;
        
        // Limit to maximum 5 files
        if (currentFileCount + files.length > maxFiles) {
            alert(`You can upload a maximum of ${maxFiles} files. You currently have ${currentFileCount} files selected.`);
            return;
        }
        
        const newFiles = files.slice(0, maxFiles - currentFileCount);
        const updatedFiles = [...uploadedFiles, ...newFiles];
        
        setUploadedFiles(updatedFiles);
        setData('files', updatedFiles);
        
        // Create previews for images
        newFiles.forEach((file, index) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const newPreview = e.target.result;
                    setFilePreviews(prevPreviews => [...prevPreviews, newPreview]);
                };
                reader.readAsDataURL(file);
            } else {
                setFilePreviews(prevPreviews => [...prevPreviews, null]);
            }
        });
    };

    const removeFile = (index) => {
        const updatedFiles = uploadedFiles.filter((_, i) => i !== index);
        const updatedPreviews = filePreviews.filter((_, i) => i !== index);
        
        setUploadedFiles(updatedFiles);
        setFilePreviews(updatedPreviews);
        setData('files', updatedFiles);
    };

    const submit = (e) => {
        e.preventDefault();
        
        if (uploadedFiles.length === 0) {
            alert('Please select at least one file to upload.');
            return;
        }
        
        setIsUploading(true);
        
        post(route('receipts.store'), {
            onFinish: () => {
                setIsUploading(false);
                reset('files');
                setFilePreviews([]);
                setUploadedFiles([]);
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
                                        Select Receipt Files (Max 5 files)
                                    </label>
                                    
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        {/* Camera capture for mobile */}
                                        <div>
                                            <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                                                <div className="flex flex-col items-center justify-center pt-5 pb-6">
                                                    <i className="fas fa-camera text-3xl mb-4 text-gray-500"></i>
                                                    <p className="mb-2 text-sm text-gray-500">
                                                        <span className="font-semibold">Scan with Camera</span>
                                                    </p>
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        capture="environment"
                                                        multiple
                                                        onChange={handleFileChange}
                                                        className="hidden"
                                                    />
                                                </div>
                                            </label>
                                        </div>

                                        {/* File upload */}
                                        <div>
                                            <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                                                <div className="flex flex-col items-center justify-center pt-5 pb-6">
                                                    <i className="fas fa-upload text-3xl mb-4 text-gray-500"></i>
                                                    <p className="mb-2 text-sm text-gray-500">
                                                        <span className="font-semibold">Upload Photos/PDFs</span>
                                                    </p>
                                                    <input
                                                        type="file"
                                                        accept=".jpg,.jpeg,.png,.heic,.webp,.pdf"
                                                        multiple
                                                        onChange={handleFileChange}
                                                        className="hidden"
                                                    />
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    {/* File counter */}
                                    <div className="text-sm text-gray-600 mb-4">
                                        {uploadedFiles.length} of 5 files selected
                                    </div>

                                    {/* Selected files preview */}
                                    {uploadedFiles.length > 0 && (
                                        <div className="space-y-3">
                                            <h3 className="text-lg font-medium text-gray-900">Selected Files:</h3>
                                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                                {uploadedFiles.map((file, index) => (
                                                    <div key={index} className="relative bg-gray-50 rounded-lg p-3 border">
                                                        <button
                                                            type="button"
                                                            onClick={() => removeFile(index)}
                                                            className="absolute top-2 right-2 text-red-500 hover:text-red-700"
                                                        >
                                                            <XMarkIcon className="h-5 w-5" />
                                                        </button>
                                                        
                                                        {filePreviews[index] ? (
                                                            <img
                                                                src={filePreviews[index]}
                                                                alt={`Preview ${index + 1}`}
                                                                className="w-full h-24 object-cover rounded mb-2"
                                                            />
                                                        ) : (
                                                            <div className="w-full h-24 bg-gray-200 rounded mb-2 flex items-center justify-center">
                                                                <i className="fas fa-file-pdf text-2xl text-gray-500"></i>
                                                            </div>
                                                        )}
                                                        
                                                        <div className="text-xs text-gray-600 truncate">
                                                            <strong>Type:</strong> {file.type}
                                                        </div>
                                                        <div className="text-xs text-gray-600 truncate">
                                                            <strong>Size:</strong> {(file.size / 1024 / 1024).toFixed(2)} MB
                                                        </div>
                                                        <div className="text-xs text-gray-600 truncate">
                                                            <strong>Name:</strong> {file.name}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    <InputError message={errors.file} className="mt-2" />
                                </div>

                                {/* File Preview - removed duplicate preview section */}

                                {/* File Info - removed duplicate file info section */}

                                <div className="flex items-center justify-end space-x-4">
                                    <CancelButton 
                                        icon={XMarkIcon}
                                        iconOnly
                                        variant="link"
                                        href={route('receipts.index')}
                                    />
                                    <PrimaryButton 
                                        icon={CheckIcon}
                                        iconOnly
                                        disabled={processing || uploadedFiles.length === 0 || isUploading}
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