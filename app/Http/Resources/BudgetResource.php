<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Wraps one item returned by BudgetService::progress(). */
class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return [
            'id' => $item['budget']->id,
            'category_id' => $item['budget']->category_id,
            'name' => $item['name'],
            'color' => $item['color'],
            'limit' => number_format($item['limit'], 2, '.', ''),
            'spent' => number_format($item['spent'], 2, '.', ''),
            'remaining' => number_format($item['remaining'], 2, '.', ''),
            'percent' => $item['percent'],
            'status' => $item['status'],
        ];
    }
}
