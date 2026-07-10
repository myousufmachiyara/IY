// database/migrations/2026_01_02_000011_create_invoices_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();

            $table->unsignedBigInteger('sale_price')->default(0);
            $table->unsignedBigInteger('settled_amount')->default(0); // discount adjustment
            $table->unsignedBigInteger('total_payable')->default(0);  // sale_price - settled
            $table->unsignedBigInteger('amount_paid')->default(0);

            $table->date('due_first')->nullable();   // 50% (7 + 8 grace)
            $table->date('due_final')->nullable();    // remaining 50% before arrival
            $table->string('status')->default('draft'); // draft|issued|partial|paid|cancelled

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('invoices'); }
};