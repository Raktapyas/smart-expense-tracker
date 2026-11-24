<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRuleRequest;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Services\CategorySuggester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryRuleController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $categories = Category::availableTo($user->id)->orderBy('type')->orderBy('name')->get();

        return view('rules.index', [
            'rules' => $user->categoryRules()->with('category')->orderBy('keyword')->get(),
            'categoryOptions' => ['' => 'Choose a category']
                + $categories->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.$c->type->label()])->all(),
        ]);
    }

    public function store(CategoryRuleRequest $request, CategorySuggester $suggester): RedirectResponse
    {
        $rule = $request->user()->categoryRules()->create($request->safe()->only(['keyword', 'category_id']));

        $count = $request->boolean('apply_existing') ? $suggester->applyToExisting($request->user(), $rule) : 0;

        $status = $count > 0
            ? "Rule saved. {$count} existing ".Str::plural('transaction', $count).' categorised.'
            : 'Rule saved.';

        return redirect()->route('rules.index')->with('status', $status);
    }

    public function destroy(CategoryRule $rule): RedirectResponse
    {
        Gate::authorize('delete', $rule);

        $rule->delete();

        return redirect()->route('rules.index')->with('status', 'Rule deleted.');
    }
}
