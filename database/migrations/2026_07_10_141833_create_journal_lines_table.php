// database/migrations/2026_01_02_000010_create_journal_lines_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->nullableMorphs('party');   // customer / vendor subledger
            $table->string('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('journal_lines'); }
};