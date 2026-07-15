// database/migrations/2026_01_02_000002_create_vehicles_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete(); // denormalised for scoping
            $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();;

            // requirement
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
            $table->string('grade')->nullable();
            $table->string('chassis_no')->nullable();
            $table->unsignedBigInteger('budget')->default(0); // yen (customer budget)

            // result (filled when won)
            $table->unsignedBigInteger('buying_price')->nullable();   // winning price
            $table->unsignedBigInteger('selling_price')->nullable();  // agent-set
            $table->string('winning_screenshot_path')->nullable();
            $table->timestamp('won_at')->nullable();

            // requirement | bidding | won | lost | invoiced | dispatched | arrived | delivered
            $table->string('status')->default('requirement');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('vehicles'); }
};