<?php

namespace App\Console\Commands;

use App\Enums\DeadlinesStatus;
use App\Enums\TaskStatus;
use App\Jobs\MarkTaskOverdue;
use App\Models\Task;
use Illuminate\Console\Command;

class MarkOverdueTasks extends Command
{
    protected $signature = 'tasks:mark-overdue';

    protected $description = 'Dispatch jobs that mark tasks with passed deadline as overdue';

    public function handle(): int
    {
        Task::whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', TaskStatus::DONE->value))
            ->where('deadline_status', '!=', DeadlinesStatus::OVERDUE->value)
            ->select('id')
            ->chunkById(500, function ($tasks) {
                foreach ($tasks as $task) {
                    MarkTaskOverdue::dispatch($task->id);
                }
            });

        return self::SUCCESS;
    }
}