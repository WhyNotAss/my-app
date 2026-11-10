<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\ListTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TaskController extends Controller
{
    public function index(ListTasksRequest $request): JsonResponse
    {
        $v = $request->validated();

        $tasks = Task::query()
            ->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($v['deadline_status'] ?? null, fn ($q, $s) => $q->where('deadline_status', $s))
            ->orderBy('deadline')
            ->paginate($v['per_page'] ?? 15)
            ->withQueryString();

        return response()->json($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = Task::create($request->validated());

        return response()->json(['data' => $task->refresh()], 201);
    }

    public function show(int $id): JsonResponse
    {
        $key = "task:{$id}";

        $data = Cache::get($key);
        if ($data !== null) {
            return response()->json(['data' => $data, 'cached' => true]);
        }

        $task = Task::find($id);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $data = $task->toArray();
        Cache::put($key, $data, 60);

        return response()->json(['data' => $data, 'cached' => false]);
    }

    public function updateStatus(UpdateTaskStatusRequest $request, int $id): JsonResponse
    {
        $task = Task::find($id);
        if (! $task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $newStatus = TaskStatus::from($request->validated('status'));

        if (! $task->status->canTransitionTo($newStatus)) {
            return response()->json([
                'message' => "Cannot change status from {$task->status->value} to {$newStatus->value}",
                'allowed' => array_map(fn ($s) => $s->value, $task->status->allowedTransitions()),
            ], 422);
        }

        $task->status = $newStatus;
        $task->save();

        return response()->json(['data' => $task]);
    }
}