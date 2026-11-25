<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::availableTo($request->user()->id)->orderBy('type')->orderBy('name')->get()
        );
    }
}
