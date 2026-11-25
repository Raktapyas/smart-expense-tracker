<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Expects the account to be loaded with Account::withBalance(). */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'currency' => $this->currency,
            'opening_balance' => number_format((float) $this->opening_balance, 2, '.', ''),
            'balance' => number_format((float) $this->balance, 2, '.', ''),
            'color' => $this->color,
            'icon' => $this->icon,
            'is_archived' => (bool) $this->is_archived,
        ];
    }
}
