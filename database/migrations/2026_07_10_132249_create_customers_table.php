// database/migrations/2026_01_02_000001_create_customers_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_no')->unique()->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('consignee_name')->nullable();
            $table->text('address')->nullable();

            // assigned sales agent (creator owns the customer)
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();

            $table->unsignedBigInteger('security_deposit')->default(0);   // yen, refundable
            $table->boolean('security_deposit_paid')->default(false);
            $table->string('security_deposit_status')->default('none'); // none|pending|approved|rejected
            $table->string('security_deposit_account')->nullable();
            $table->foreignId('security_deposit_received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('security_deposit_received_at')->nullable();
            $table->foreignId('security_deposit_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('security_deposit_approved_at')->nullable();
            $table->text('security_deposit_rejection_reason')->nullable();
            $table->boolean('security_deposit_refunded')->default(false);

            $table->timestamp('profile_completed_at')->nullable();        // bidding gate
            $table->string('status')->default('active');                  // active | inactive
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('customers'); }
};