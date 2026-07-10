// database/migrations/2026_01_02_000005_create_bids_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_sheet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();

            $table->string('lot_no')->nullable();
            $table->string('auction_house')->nullable();
            $table->date('auction_date')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
            $table->string('grade')->nullable();
            $table->string('chassis_no')->nullable();
            $table->unsignedBigInteger('max_bid')->default(0); // yen

            $table->string('result')->default('pending');      // pending | won | lost
            $table->unsignedBigInteger('won_amount')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('bids'); }
};