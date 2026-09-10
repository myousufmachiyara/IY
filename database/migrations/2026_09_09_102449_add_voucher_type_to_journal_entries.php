<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entries', 'voucher_type')) {
                $table->string('voucher_type')->default('journal')->after('entry_no');
            }
        });
    }
    public function down(): void {
        Schema::table('journal_entries', fn (Blueprint $t) => $t->dropColumn('voucher_type'));
    }
};