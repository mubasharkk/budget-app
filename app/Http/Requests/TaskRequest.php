<?php

namespace App\Http\Requests;

use App\Enums\BillingCycle;
use App\Enums\TaskLinkType;
use App\Enums\TaskPriority;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date', 'required_with:repeats'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999'],
            'repeats' => ['nullable', Rule::enum(BillingCycle::class)],
            'link_type' => ['nullable', Rule::enum(TaskLinkType::class)],
            'link_id' => ['nullable', 'required_with:link_type', 'integer', $this->ownedLink(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'What needs doing?',
            'title.max' => 'Keep the task to 255 characters or fewer.',
            'due_date.required_with' => 'A repeating task needs a deadline.',
            'due_date.date' => 'Enter a valid deadline.',
            'priority.enum' => 'Choose a low, normal or high priority.',
            'amount.numeric' => 'Enter the amount as a number.',
            'amount.min' => 'The amount payable must be greater than zero.',
            'repeats.enum' => 'Choose how often the task repeats.',
            'link_type.enum' => 'Tasks can be linked to a receipt, contract, budget or savings entry.',
            'link_id.required_with' => 'Choose what the task is about.',
        ];
    }

    /**
     * Attributes for the Task model; the link becomes taskable_type / taskable_id.
     *
     * @return array<string, mixed>
     */
    public function taskData(): array
    {
        $data = $this->safe()->except(['link_type', 'link_id']);
        $linkType = TaskLinkType::tryFrom((string) $this->validated('link_type'));

        return [
            ...$data,
            'priority' => $data['priority'] ?? TaskPriority::Normal->value,
            'taskable_type' => $linkType?->modelClass(),
            'taskable_id' => $linkType !== null ? (int) $this->validated('link_id') : null,
        ];
    }

    private function ownedLink(string $attribute, mixed $value, Closure $fail): void
    {
        $linkType = TaskLinkType::tryFrom((string) $this->input('link_type'));

        if ($linkType !== null && $linkType->findOwnedBy($this->user()->id, (int) $value) === null) {
            $fail("That {$linkType->label()} does not exist.");
        }
    }
}
