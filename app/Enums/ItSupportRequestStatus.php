<?php

namespace App\Enums;

enum ItSupportRequestStatus: string
{
    case Submitted = 'submitted';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
