// database/migrations/2026_01_02_000012_create_payments_table.php  (customer money-in)
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // both nullable: payment can be vehicle/invoice-wise OR against total balance
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedBigInteger('amount'); // yen
            $table->string('method')->nullable(); // bank | cash
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->date('paid_at');
            $table->string('reference')->nullable();
            $table->boolean('is_backdated')->default(false); // only super admin / accountant
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('payments'); }
};