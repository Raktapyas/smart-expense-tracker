<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRuleRequest;
use App\Http\Resources\RuleResource;
use App\Models\CategoryRule;
use App\Services\CategorySuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RuleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return RuleResource::collection(
            $request->user()->categoryRules()->with('category')->orderBy('keyword')->get()
        );
    }

    /** Send "apply_existing": true to also categorise matching uncategorised transactions. */
    public function store(CategoryRuleRequest $request, CategorySuggester $suggester): JsonResponse
    {
        $rule = $request->user()->categoryRules()->create($request->safe()->only(['keyword', 'category_id']));

        $applied = $request->boolean('apply_existing') ? $suggester->applyToExisting($request->user(), $rule) : 0;

        return (new RuleResource($rule->load('category')))
            ->additional(['meta' => ['applied_to_existing' => $applied]])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(CategoryRule $rule): Response
    {
        Gate::authorize('delete', $rule);

        $rule->delete();

        return response()->noContent();
    }
}
