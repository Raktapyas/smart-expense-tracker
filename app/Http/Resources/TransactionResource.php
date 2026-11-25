<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'amount' => number_format((float) $this->amount, 2, '.', ''),
            'occurred_on' => $this->occurred_on->format('Y-m-d'),
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
            'recurring_transaction_id' => $this->recurring_transaction_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
