<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'account_date')) {
                $table->date('account_date')->nullable()->after('status');
            }
        });
    }
    public function down(): void {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('account_date'));
    }
};