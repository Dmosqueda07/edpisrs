<?php

namespace App\Http\Controllers;

use App\Models\ItSupportRequest;
use App\Services\RequestDashboardMetrics;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(RequestDashboardMetrics $metrics): View
    {
        $user = request()->user();
        abort_unless($user, 403);

        if (Gate::allows('manageAssignments', ItSupportRequest::class)) {
            $cards = $metrics->administratorCards();
            $dashboardType = 'administrator';
        } elseif (Gate::allows('viewAssigned', ItSupportRequest::class)) {
            $cards = $metrics->technicianCards($user);
            $dashboardType = 'technician';
        } else {
            $cards = $metrics->employeeCards($user);
            $dashboardType = 'employee';
        }

        return view('dashboard', compact('cards', 'dashboardType'));
    }
}
