<?php

namespace App\Models\Concerns;

use App\Models\Task;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A record tasks can be linked to. Deleting the record keeps its tasks and
 * only removes the link, so a to-do is never lost with the thing it was about.
 */
trait HasTasks
{
    public static function bootHasTasks(): void
    {
        static::deleting(function (self $model): void {
            $model->tasks()->update(['taskable_type' => null, 'taskable_id' => null]);
        });
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }
}
