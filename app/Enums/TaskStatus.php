<?php

namespace App\Enums;

enum TaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';

    /** @return array<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::TODO => [self::IN_PROGRESS],
            self::IN_PROGRESS => [self::TODO, self::DONE],
            self::DONE => [self::IN_PROGRESS],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}