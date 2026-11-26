<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'frequency' => $this->frequency->value,
            'frequency_label' => $this->frequency->label(),
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'merchant' => $this->merchant,
            'note' => $this->note,
            'account_id' => $this->account_id,
            'transfer_account_id' => $this->transfer_account_id,
            'category_id' => $this->category_id,
            'account' => $this->whenLoaded('account', fn () => [
                'id' => $this->account->id,
                'name' => $this->account->name,
            ]),
            'transfer_account' => $this->whenLoaded('transferAccount', fn () => $this->transferAccount ? [
                'id' => $this->transferAccount->id,
                'name' => $this->transferAccount->name,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'color' => $this->category->color,
                'icon' => $this->category->icon,
            ] : null),
            'starts_on' => $this->starts_on->format('Y-m-d'),
            'next_run_on' => $this->next_run_on->format('Y-m-d'),
            'ends_on' => $this->ends_on?->format('Y-m-d'),
            'last_run_on' => $this->last_run_on?->format('Y-m-d'),
            'is_active' => (bool) $this->is_active,
            'status' => $this->statusLabel(),
        ];
    }
}
