<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'invoice_type')) {
                $table->string('invoice_type')->default('cnf')->after('invoice_no');
            }
        });
        // vehicle_id must become nullable — a deposit invoice precedes any specific vehicle.
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->change();
        });
    }
    public function down(): void {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_type');
            $table->foreignId('vehicle_id')->nullable(false)->change();
        });
    }
};