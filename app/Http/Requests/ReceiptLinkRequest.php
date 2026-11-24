<?php

namespace App\Http\Requests;

use App\Enums\ReceiptLinkType;
use App\Models\Receipt;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A receipt and the contract or savings entry it is attached to (or detached
 * from). Both must belong to the user.
 */
class ReceiptLinkRequest extends FormRequest
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
            'receipt_id' => ['required', 'integer', Rule::exists('receipts', 'id')->where('user_id', $this->user()->id)],
            'link_type' => ['required', Rule::enum(ReceiptLinkType::class)],
            'link_id' => ['required', 'integer', $this->ownedLink(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt_id.required' => 'Choose a receipt.',
            'receipt_id.exists' => 'That receipt does not exist.',
            'link_type.enum' => 'Receipts can be attached to a contract or savings entry.',
        ];
    }

    public function receipt(): Receipt
    {
        return Receipt::query()->findOrFail((int) $this->validated('receipt_id'));
    }

    public function linkable(): Model
    {
        return ReceiptLinkType::from($this->validated('link_type'))->findOwnedBy($this->user()->id, (int) $this->validated('link_id'));
    }

    private function ownedLink(string $attribute, mixed $value, Closure $fail): void
    {
        $linkType = ReceiptLinkType::tryFrom((string) $this->input('link_type'));

        if ($linkType !== null && $linkType->findOwnedBy($this->user()->id, (int) $value) === null) {
            $fail("That {$linkType->label()} does not exist.");
        }
    }
}
