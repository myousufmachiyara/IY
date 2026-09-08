<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'deposit_invoice_id')) {
                $table->foreignId('deposit_invoice_id')->nullable()->after('account_date')->constrained('invoices')->nullOnDelete();
            }
        });
    }
    public function down(): void {
        Schema::table('customers', fn (Blueprint $t) => $t->dropConstrainedForeignId('deposit_invoice_id'));
    }
};