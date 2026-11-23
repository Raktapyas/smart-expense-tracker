<?php

namespace App\Enums;

enum AccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Card = 'card';
    case Wallet = 'wallet';
    case Savings = 'savings';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
