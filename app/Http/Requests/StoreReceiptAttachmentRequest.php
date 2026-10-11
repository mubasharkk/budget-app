<?php

namespace App\Http\Requests;

use App\Domain\Receipts\Services\ReceiptAttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreReceiptAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('receipt'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $remaining = app(ReceiptAttachmentService::class)->remainingSlots($this->route('receipt'));

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.$remaining],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,heic,heif,webp,pdf,txt,csv,doc,docx,xls,xlsx,eml', 'max:15360'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Please select at least one document.',
            'files.max' => 'A receipt can have at most '.ReceiptAttachmentService::MAX_ATTACHMENTS.' attached documents.',
            'files.*.mimes' => 'Documents must be a photo, PDF, text, CSV, Word, Excel or email file.',
            'files.*.max' => 'Each document must be smaller than 15 MB.',
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function uploadedFiles(): array
    {
        return $this->file('files', []);
    }
}
