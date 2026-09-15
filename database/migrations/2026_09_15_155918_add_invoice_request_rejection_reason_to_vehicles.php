<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'invoice_request_rejection_reason')) {
                $table->text('invoice_request_rejection_reason')->nullable()->after('invoice_requested_at');
            }
        });
    }
    public function down(): void {
        Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn('invoice_request_rejection_reason'));
    }
};