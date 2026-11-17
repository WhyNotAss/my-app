<?php

namespace App\Jobs;

use App\Enums\DeadlinesStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskOverdueLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class MarkTaskOverdue implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $taskId) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $task = Task::lockForUpdate()->find($this->taskId);

            if (
                !$task
                || !$task->deadline
                || $task->status === TaskStatus::DONE
                || $task->deadline_status === DeadlinesStatus::OVERDUE
            ) {
                return; // идемпотентность: повторный запуск ничего не сломает
            }

            $new = DeadlinesStatus::fromDeadline($task->deadline);
            if ($new !== DeadlinesStatus::OVERDUE) {
                return; // дедлайн успели перенести
            }

            $task->deadline_status = $new;
            $task->save(); // хук saved сам сбросит кэш task:{id}

            TaskOverdueLog::create([
                'task_id'    => $task->id,
                'overdue_at' => now(),
            ]);
        });
    }
}
