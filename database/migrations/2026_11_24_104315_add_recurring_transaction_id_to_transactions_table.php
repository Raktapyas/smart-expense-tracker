<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unique(['recurring_transaction_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['recurring_transaction_id', 'occurred_on']);
            $table->dropColumn('recurring_transaction_id');
        });
    }
};
