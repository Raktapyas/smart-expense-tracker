<?php

namespace App\Enums;

enum Frequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
