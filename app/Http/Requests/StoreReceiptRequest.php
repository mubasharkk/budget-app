<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => 'required|array|min:1|max:5',
            'files.*' => 'required|file|mimes:jpg,jpeg,png,heic,heif,webp,pdf|max:15360',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Please select at least one receipt image or PDF.',
            'files.max' => 'You can upload a maximum of 5 files at once.',
            'files.*.mimes' => 'Receipts must be a photo (JPG, PNG, HEIC, WebP) or PDF.',
            'files.*.max' => 'Each file must be smaller than 15 MB.',
        ];
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    public function uploadedFiles(): array
    {
        return $this->file('files', []);
    }
}
