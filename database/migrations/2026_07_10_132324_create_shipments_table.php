// database/migrations/2026_01_02_000006_create_shipments_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('method');                        // RORO | Container
            $table->date('shipment_date')->nullable();       // super admin only
            $table->date('expected_arrival')->nullable();    // super admin only
            $table->unsignedBigInteger('freight_total')->default(0);
            $table->string('status')->default('preparing');  // preparing | dispatched | arrived
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('shipments'); }
};