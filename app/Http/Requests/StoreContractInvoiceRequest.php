<?php

namespace App\Http\Requests;

use App\Domain\Contracts\Services\ContractInvoiceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreContractInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('contract'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $remaining = app(ContractInvoiceService::class)->remainingSlots($this->route('contract'));

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.$remaining],
            'files.*' => ['required', 'file', 'mimes:'.ContractInvoiceService::ALLOWED_MIMES, 'max:15360'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Please select at least one invoice.',
            'files.max' => 'A contract can have at most '.ContractInvoiceService::MAX_INVOICES.' invoices.',
            'files.*.mimes' => ContractInvoiceService::MIMES_MESSAGE,
            'files.*.max' => 'Each invoice must be smaller than 15 MB.',
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
