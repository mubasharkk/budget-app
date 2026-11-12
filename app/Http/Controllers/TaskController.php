<?php

namespace App\Http\Controllers;

use App\Domain\Tasks\Services\TaskService;
use App\Http\Requests\TaskRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tasks are added, edited and ticked off from the Tasks page, the dashboard
 * widget and the pages of linked records, so writes redirect back.
 */
class TaskController extends Controller
{
    public function __construct(private TaskService $taskService) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        return Inertia::render('Tasks/Index', [
            'board' => $this->taskService->board($userId),
            'summary' => $this->taskService->summary($userId),
            'linkOptions' => $this->taskService->linkOptions($userId),
            ...$this->taskService->formOptions(),
        ]);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $this->taskService->create($request->user(), $request->taskData());

        return back()->with('success', 'Task added.');
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->taskService->update($task, $request->taskData());

        return back()->with('success', 'Task updated.');
    }

    public function complete(Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->taskService->complete($task);

        return back();
    }

    public function reopen(Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->taskService->reopen($task);

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $this->taskService->delete($task);

        return back()->with('success', 'Task deleted.');
    }
}
