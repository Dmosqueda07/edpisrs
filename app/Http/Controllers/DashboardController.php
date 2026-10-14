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
            $cards = $metrics->administratorCards();
            $assignedCards = $metrics->technicianCards($user);
            $dashboardType = 'administrator';
        } else {
            $cards = [];
            $assignedCards = [];
            $dashboardType = 'employee';
        }

        return view('dashboard', compact('cards', 'assignedCards', 'dashboardType'));
    }
}
