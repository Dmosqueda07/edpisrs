<?php

namespace App\Enums;

enum ItSupportRequestStatus: string
{
    case PendingApproval = 'pending_approval';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
}
