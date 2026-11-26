<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Wraps one item returned by InsightService::report()['insights']. */
class InsightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return [
            'tone' => $item['tone'],
            'icon' => $item['icon'],
            'text' => $item['text'],
            // Mobile apps cannot open web URLs; give them a screen name instead ('budgets' or 'rules').
            'action' => $item['link'] ? [
                'label' => $item['link']['label'],
                'target' => basename((string) parse_url($item['link']['url'], PHP_URL_PATH)),
            ] : null,
        ];
    }
}
