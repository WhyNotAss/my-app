<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Enums\TaskStatus;
use App\Http\Requests\UpdateTaskStatusRequest;

class TaskController extends Controller
{
    //
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
