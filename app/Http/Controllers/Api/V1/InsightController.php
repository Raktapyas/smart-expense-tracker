<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InsightResource;
use App\Services\InsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function __invoke(Request $request, InsightService $insights): JsonResponse
    {
        $report = $insights->report($request->user());
        $money = fn (float|int $value): string => number_format((float) $value, 2, '.', '');

        return response()->json(['data' => [
            'period_label' => $report['periodLabel'],
            'insights' => InsightResource::collection($report['insights'])->resolve(),
            'comparison' => $report['comparison']->map(fn (array $row) => [
                'name' => $row['name'],
                'current' => $money($row['current']),
                'previous' => $money($row['previous']),
                'change' => $row['change'],
            ])->values()->all(),
        ]]);
    }
}
