<?php

namespace App\Http\Resources;

use App\Enums\TaskLinkType;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'notes' => $this->notes,
            'due_date' => $this->due_date?->toDateString(),
            'priority' => $this->priority->value,
            'amount' => $this->amount,
            'repeats' => $this->repeats?->value,
            'repeats_label' => $this->repeats?->label(),
            'is_done' => $this->isDone(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'link' => $this->link(),
        ];
    }

    /**
     * @return array{type: string, id: int, label: string, url: string}|null
     */
    private function link(): ?array
    {
        $linked = $this->taskable_type !== null ? $this->taskable : null;
        $type = $linked !== null ? TaskLinkType::fromModel($linked) : null;

        if ($type === null) {
            return null;
        }

        return [
            'type' => $type->value,
            'id' => $linked->getKey(),
            'label' => $type->describe($linked),
            'url' => route($type->routeName(), $linked->getKey()),
        ];
    }
}
