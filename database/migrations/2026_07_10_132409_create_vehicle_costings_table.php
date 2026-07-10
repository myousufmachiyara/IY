// database/migrations/2026_01_02_000003_create_vehicle_costings_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicle_costings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->unique()->constrained()->cascadeOnDelete();

            // costing side
            $table->unsignedBigInteger('buying_price')->default(0);
            $table->decimal('vendor_commission_percent', 5, 2)->default(7);   // editable per vehicle
            $table->unsignedBigInteger('vendor_commission_amount')->default(0);
            $table->unsignedBigInteger('inland_charges')->default(0);
            $table->unsignedBigInteger('auction_commission')->default(0);
            $table->unsignedBigInteger('freight_charges')->default(0);
            $table->unsignedBigInteger('misc_expenses')->default(0);
            $table->unsignedBigInteger('total_costing')->default(0);

            // pricing / profit side
            $table->unsignedBigInteger('company_service_charge')->default(0); // tiered
            $table->unsignedBigInteger('sale_price')->default(0);
            $table->bigInteger('profit')->default(0);                         // signed
            $table->unsignedBigInteger('agent_commission_amount')->default(0);// profit * 15%
            $table->unsignedBigInteger('agent_bonus')->default(0);            // fixed per won bid

            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('vehicle_costings'); }
};