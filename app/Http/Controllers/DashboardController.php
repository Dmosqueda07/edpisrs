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

        if (Gate::allows('viewAdminDashboard', ItSupportRequest::class)) {
            $adminDashboard = $metrics->administratorDashboard($user);
            $cards = $adminDashboard['overview'];
            $assignedCards = [];
            $assignedCount = $adminDashboard['assignedCount'];
            $shortcuts = $adminDashboard['shortcuts'];
            $dashboardType = 'administrator';
        } else {
            $cards = [];
            $assignedCards = [];
            $assignedCount = 0;
            $shortcuts = [];
            $dashboardType = 'employee';
        }

        return view('dashboard', compact('cards', 'assignedCards', 'assignedCount', 'shortcuts', 'dashboardType'));
    }
}
