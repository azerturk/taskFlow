<?php

namespace Modules\Dashboard\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Dashboard\Services\DashboardService;

class DashboardController
{
    use AuthorizesRequests;

    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): View
    {
        $this->authorize('viewDashboard');

        return view('dashboard::index', $this->dashboard->page($request->user()));
    }
}
