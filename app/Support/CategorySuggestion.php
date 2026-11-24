<?php

namespace App\Support;

use App\Models\Category;

final class CategorySuggestion
{
    public function __construct(
        public readonly Category $category,
        public readonly string $source, // 'rule' or 'history'
    ) {}
}
