<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Seeder;

class DefaultCategorySeeder extends Seeder
{
    public function run(): void
    {
        $expense = [
            ['Food & Dining', 'utensils', '#F97316'],
            ['Groceries', 'shopping-basket', '#22C55E'],
            ['Transport', 'car', '#3B82F6'],
            ['Housing & Rent', 'home', '#8B5CF6'],
            ['Utilities', 'zap', '#EAB308'],
            ['Health', 'heart-pulse', '#EF4444'],
            ['Shopping', 'shopping-bag', '#EC4899'],
            ['Entertainment', 'film', '#06B6D4'],
            ['Education', 'graduation-cap', '#6366F1'],
            ['Travel', 'plane', '#14B8A6'],
            ['Other', 'circle-ellipsis', '#64748B'],
        ];

        $income = [
            ['Salary', 'briefcase', '#10B981'],
            ['Business', 'store', '#0EA5E9'],
            ['Gifts', 'gift', '#F472B6'],
            ['Investments', 'trending-up', '#84CC16'],
            ['Other', 'circle-ellipsis', '#64748B'],
        ];

        foreach ([[CategoryType::Expense, $expense], [CategoryType::Income, $income]] as [$type, $rows]) {
            foreach ($rows as [$name, $icon, $color]) {
                Category::firstOrCreate(
                    ['user_id' => null, 'name' => $name, 'type' => $type->value],
                    ['icon' => $icon, 'color' => $color],
                );
            }
        }
    }
}
