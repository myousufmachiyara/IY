// database/migrations/2026_01_02_000004_create_bid_sheets_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bid_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path')->nullable();      // original uploaded excel
            $table->date('auction_date')->nullable();
            $table->unsignedInteger('rows_count')->default(0);
            $table->string('status')->default('uploaded'); // uploaded | shared_to_vendor
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('bid_sheets'); }
};