<?php

namespace App\Enums;

/**
 * Where a walk is in its life. A logged walk is created already completed;
 * the others arrive with plans (docs/SPEC.md §8).
 */
enum WalkStatus: string
{
    case Draft = 'draft';
    case Polling = 'polling';
    case Planned = 'planned';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
