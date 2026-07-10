// database/migrations/2026_01_02_000015_create_expenses_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category');           // salary | office | utilities | ...
            $table->string('description')->nullable();
            $table->unsignedBigInteger('amount');
            $table->date('expense_date');
            $table->foreignId('paid_from_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_backdated')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('expenses'); }
};