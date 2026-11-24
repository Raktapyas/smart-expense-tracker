<?php

namespace App\Http\Controllers;

use App\Services\InsightService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InsightController extends Controller
{
    public function __invoke(Request $request, InsightService $insights): View
    {
        return view('insights.index', $insights->report($request->user()));
    }
}
