<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Http\Resources\BudgetResource;
use App\Http\Resources\InsightResource;
use App\Http\Resources\TransactionResource;
use App\Services\DashboardService;
use App\Services\RecurringTransactionRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard, RecurringTransactionRunner $recurring): JsonResponse
    {
        $user = $request->user();

        // Catch up on recurring transactions that came due while nothing was running.
        $recurring->runFor($user);

        $data = $dashboard->build($user);
        $money = fn (float|int $value): string => number_format((float) $value, 2, '.', '');

        return response()->json(['data' => [
            'month_label' => $data['monthLabel'],
            'total_balance' => $money($data['totalBalance']),
            'income' => $money($data['income']),
            'expense' => $money($data['expense']),
            'saved' => $money($data['saved']),
            'accounts' => AccountResource::collection($data['accounts'])->resolve(),
            'category_breakdown' => $data['categoryBreakdown']->map(fn (array $row) => [
                'name' => $row['name'],
                'color' => $row['color'],
                'total' => $money($row['total']),
                'percent' => $row['percent'],
            ])->values()->all(),
            'trend' => $data['trend']->map(fn (array $month) => [
                'label' => $month['label'],
                'income' => $money($month['income']),
                'expense' => $money($month['expense']),
            ])->values()->all(),
            'recent' => TransactionResource::collection($data['recent'])->resolve(),
            'budgets' => BudgetResource::collection($data['budgets'])->resolve(),
            'over_budget_count' => $data['overBudgetCount'],
            'insights' => InsightResource::collection($data['insights'])->resolve(),
        ]]);
    }
}
