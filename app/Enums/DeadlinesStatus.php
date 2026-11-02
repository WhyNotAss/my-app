<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum DeadlinesStatus: string
{
    case OVERDUE = 'overdue';
    case DUE_SOON = 'due_soon';
    case ON_TRACK = 'on_track';


    public static function fromDeadline(Carbon $deadline): self
    {
        if ($deadline->isPast()) return self::OVERDUE;
        if ($deadline->lte(now()->addDay())) return self::DUE_SOON;
        return self::ON_TRACK;
    }
}