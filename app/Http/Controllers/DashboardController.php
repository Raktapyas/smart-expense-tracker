<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\RecurringTransactionRunner;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard, RecurringTransactionRunner $recurring): View
    {
        // Catch up on recurring transactions that came due while the app was not running.
        $recurring->runFor($request->user());

        return view('dashboard', $dashboard->build($request->user()));
    }
}
