<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskOverdueLog extends Model
{
    protected $fillable = ['task_id', 'overdue_at'];
}
