<?php

namespace App\Models;

use App\Enums\DeadlinesStatus;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Task extends Model
{
    /** @usHasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;
    protected $fillable = ['title','description','deadline'];
    protected $casts = [
        'deadline' => 'datetime',
        'deadline_status' => DeadlinesStatus::class,
        'status' => TaskStatus::class,
    ];  

    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            $task->deadline_status = DeadlinesStatus::fromDeadline($task->deadline);
        });

        static::saved(fn (Task $task) => Cache::forget("task:{$task->id}"));
        static::deleted(fn (Task $task) => Cache::forget("task:{$task->id}"));
    }
}
