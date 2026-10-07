<?php

namespace App\Enums;

enum ItSupportRequestStatus: string
{
    case PendingApproval = 'pending_approval';
    case Submitted = 'submitted';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
