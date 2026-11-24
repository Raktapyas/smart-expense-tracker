<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request, BudgetService $budgets): View
    {
        return view('budgets.index', [
            'items' => $budgets->progress($request->user()),
            'monthLabel' => now()->format('F Y'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('budgets.create', $this->formData($request));
    }

    public function store(BudgetRequest $request): RedirectResponse
    {
        $request->user()->budgets()->create($request->validated());

        return redirect()->route('budgets.index')->with('status', 'Budget saved.');
    }

    public function edit(Request $request, Budget $budget): View
    {
        Gate::authorize('update', $budget);

        return view('budgets.edit', ['budget' => $budget] + $this->formData($request));
    }

    public function update(BudgetRequest $request, Budget $budget): RedirectResponse
    {
        Gate::authorize('update', $budget);

        $budget->update($request->validated());

        return redirect()->route('budgets.index')->with('status', 'Budget updated.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        Gate::authorize('delete', $budget);

        $budget->delete();

        return redirect()->route('budgets.index')->with('status', 'Budget deleted.');
    }

    private function formData(Request $request): array
    {
        return [
            'categories' => Category::availableTo($request->user()->id)
                ->where('type', 'expense')
                ->orderBy('name')
                ->get(),
        ];
    }
}
