<?php

namespace App\Http\Controllers;

use App\Enums\ItSupportRequestStatus;
use App\Models\ItSupportRequest;
use App\Models\RequestType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'preset' => ['nullable', 'in:this_month,last_month,this_quarter,custom'],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'tab' => ['nullable', 'in:accomplishments,division,type,turnaround,status'],
            'technician' => ['nullable', 'integer'],
        ]);

        $preset = $validated['preset'] ?? 'this_month';
        [$start, $end] = $this->period($preset, $validated['start'] ?? null, $validated['end'] ?? null);
        $from = $start->startOfDay();
        $until = $end->addDay()->startOfDay();
        $tab = $validated['tab'] ?? 'accomplishments';
        $technicianId = $validated['technician'] ?? null;

        $completed = ItSupportRequest::query()
            ->with(['requester:user_id,first_name,last_name', 'requestType:id,name', 'technician:user_id,first_name,last_name'])
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<', $until)
            ->when($technicianId, fn ($query) => $query->where('assigned_technician_id', $technicianId))
            ->orderBy('completed_at')
            ->get(['id', 'ticket_no', 'requested_at', 'completed_at', 'requester_user_id', 'requester_name', 'division', 'request_type_id', 'assigned_technician_id', 'support_type', 'resolution_notes']);

        $base = fn () => DB::table('it_support_requests')->where('requested_at', '>=', $from)->where('requested_at', '<', $until);
        $divisionRows = (clone $base())->select('division', DB::raw('COUNT(*) as total'))->groupBy('division')->orderBy('division')->get();
        $typeRows = (clone $base())->leftJoin('request_types', 'it_support_requests.request_type_id', '=', 'request_types.id')
            ->select('request_types.name as name', DB::raw('COUNT(*) as total'))->groupBy('request_types.name')->orderByDesc('total')->orderBy('name')->get();

        $statusRows = (clone $base())->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->get();
        $statusCounts = [];
        foreach (ItSupportRequestStatus::cases() as $status) {
            $statusCounts[$status->value] = (int) ($statusRows->firstWhere('status', $status->value)->total ?? 0);
        }

        $turnaroundRequests = (clone $base())->get(['request_type_id', 'assigned_technician_id', 'requested_at', 'assigned_at', 'completed_at']);
        $typeNames = RequestType::query()->pluck('name', 'id');
        $technicians = User::query()->whereIn('user_id', $turnaroundRequests->pluck('assigned_technician_id')->filter()->unique())
            ->get(['user_id', 'first_name', 'last_name'])->keyBy('user_id');
        $turnaround = ['types' => [], 'technicians' => []];
        foreach ($turnaroundRequests as $row) {
            $typeName = $typeNames[$row->request_type_id] ?? 'Unknown type';
            $this->addDuration($turnaround['types'], $typeName, $row);
            $technicianName = $technicians[$row->assigned_technician_id]->full_name ?? 'Unassigned';
            $this->addDuration($turnaround['technicians'], $technicianName, $row);
        }
        foreach (['types', 'technicians'] as $group) {
            foreach ($turnaround[$group] as &$metrics) {
                foreach (['assignment', 'completion'] as $metric) {
                    $metrics[$metric] = $metrics[$metric]['count'] ? $this->duration((int) round($metrics[$metric]['seconds'] / $metrics[$metric]['count'])) : 'No data';
                }
            }
            unset($metrics);
        }

        $divisionTotal = (int) $divisionRows->sum('total');
        $technicianOptions = User::technicians()->orderBy('first_name')->orderBy('last_name')->get(['user_id', 'first_name', 'last_name']);

        return view('reports.index', compact('preset', 'start', 'end', 'tab', 'technicianId', 'completed', 'divisionRows', 'divisionTotal', 'typeRows', 'statusCounts', 'turnaround', 'technicianOptions'));
    }

    private function period(string $preset, ?string $start, ?string $end): array
    {
        $today = CarbonImmutable::today();
        return match ($preset) {
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->startOfQuarter(), $today->endOfQuarter()],
            'custom' => [CarbonImmutable::parse($start ?? $today->startOfMonth()->toDateString()), CarbonImmutable::parse($end ?? $today->toDateString())],
            default => [$today->startOfMonth(), $today->endOfMonth()],
        };
    }

    private function addDuration(array &$groups, string $key, object $request): void
    {
        $groups[$key] ??= ['assignment' => ['seconds' => 0, 'count' => 0], 'completion' => ['seconds' => 0, 'count' => 0]];
        $requested = $request->requested_at ? CarbonImmutable::parse($request->requested_at) : null;
        $assigned = $request->assigned_at ? CarbonImmutable::parse($request->assigned_at) : null;
        $completed = $request->completed_at ? CarbonImmutable::parse($request->completed_at) : null;
        if ($requested && $assigned && $assigned->greaterThanOrEqualTo($requested)) {
            $groups[$key]['assignment']['seconds'] += $requested->diffInSeconds($assigned);
            $groups[$key]['assignment']['count']++;
        }
        if ($assigned && $completed && $completed->greaterThanOrEqualTo($assigned)) {
            $groups[$key]['completion']['seconds'] += $assigned->diffInSeconds($completed);
            $groups[$key]['completion']['count']++;
        }
    }

    private function duration(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($days) $parts[] = $days.' '.($days === 1 ? 'day' : 'days');
        if ($hours) $parts[] = $hours.' '.($hours === 1 ? 'hour' : 'hours');
        if ($minutes && count($parts) < 2) $parts[] = $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        return $parts ? implode(' ', $parts) : 'Less than a minute';
    }
}
