<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\User;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BudgetController extends Controller
{
    /** Budgets for the current calendar month with progress, most-used first. */
    public function index(Request $request, BudgetService $budgets): AnonymousResourceCollection
    {
        return BudgetResource::collection($budgets->progress($request->user()))
            ->additional(['meta' => ['month' => now()->format('Y-m')]]);
    }

    public function store(BudgetRequest $request, BudgetService $budgets): JsonResponse
    {
        $budget = $request->user()->budgets()->create($request->validated());

        return (new BudgetResource($this->progressFor($budgets, $request->user(), $budget)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(BudgetRequest $request, Budget $budget, BudgetService $budgets): BudgetResource
    {
        Gate::authorize('update', $budget);

        $budget->update($request->validated());

        return new BudgetResource($this->progressFor($budgets, $request->user(), $budget));
    }

    public function destroy(Budget $budget): Response
    {
        Gate::authorize('delete', $budget);

        $budget->delete();

        return response()->noContent();
    }

    private function progressFor(BudgetService $budgets, User $user, Budget $budget): array
    {
        return $budgets->progress($user)->first(fn (array $item) => $item['budget']->id === $budget->id);
    }
}
