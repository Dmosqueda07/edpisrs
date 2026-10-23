<?php

namespace App\Http\Controllers;

use App\Models\ItSupportRequestLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    private const ACTIONS = [
        'assigned' => 'Assigned the request',
        'reassigned' => 'Reassigned the request',
        'priority_changed' => 'Changed the priority',
        'approval_blocked_self' => 'Blocked a self-approval attempt',
        'approval_decision' => 'Recorded an approval decision',
        'work_started' => 'Started work',
        'resolved' => 'Marked as resolved',
        'requester_confirmed' => 'Confirmed completion',
        'cancelled' => 'Cancelled the request',
        'reopened' => 'Reopened the request',
        'internal_note_added' => 'Added an internal note',
        'comment_added' => 'Added a comment',
        'attachment_uploaded' => 'Uploaded an attachment',
        'submitted' => 'Submitted the request',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'in:requests,accounts'],
            'search' => ['nullable', 'string', 'max:100'],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'action' => ['nullable', 'in:'.implode(',', array_keys(self::ACTIONS))],
        ]);
        $tab = $filters['tab'] ?? 'requests';
        if ($tab === 'accounts') {
            Gate::authorize('viewAccountActivityLogs');
        }

        $logs = null;
        if ($tab === 'requests') {
            $logs = ItSupportRequestLog::query()
                ->with([
                    'user:user_id,first_name,last_name,role',
                    'itSupportRequest:id,ticket_no,requester_name,requester_user_id',
                    'itSupportRequest.requester:user_id,first_name,last_name',
                ])
                ->when($filters['search'] ?? null, function ($query, string $search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->whereHas('user', fn ($user) => $user->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'))
                            ->orWhereHas('itSupportRequest', function ($request) use ($search): void {
                                $request->where('ticket_no', 'like', '%'.$search.'%')
                                    ->orWhere('requester_name', 'like', '%'.$search.'%')
                                    ->orWhereHas('requester', fn ($requester) => $requester->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
                            });
                    });
                })
                ->when($filters['start'] ?? null, fn ($query, string $date) => $query->where('created_at', '>=', CarbonImmutable::parse($date)->startOfDay()))
                ->when($filters['end'] ?? null, fn ($query, string $date) => $query->where('created_at', '<', CarbonImmutable::parse($date)->addDay()->startOfDay()))
                ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
                ->orderByDesc('created_at')->orderByDesc('id')
                ->paginate(25)->withQueryString();
        }

        $actions = self::ACTIONS;
        return view('activity-logs.index', compact('tab', 'filters', 'logs', 'actions'));
    }

    public static function actionLabel(string $action): string
    {
        return self::ACTIONS[$action] ?? 'Recorded activity';
    }
}
