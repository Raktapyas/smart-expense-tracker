<?php

namespace App\Http\Controllers;

use App\Services\CategorySuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategorySuggestionController extends Controller
{
    public function __invoke(Request $request, CategorySuggester $suggester): JsonResponse
    {
        $merchant = $request->query('merchant');
        $type = $request->query('type');

        $suggestion = is_string($merchant) && is_string($type)
            ? $suggester->suggest($request->user(), $merchant, $type)
            : null;

        return response()->json([
            'category_id' => $suggestion?->category->id,
            'category_name' => $suggestion?->category->name,
            'source' => $suggestion?->source,
        ]);
    }
}
