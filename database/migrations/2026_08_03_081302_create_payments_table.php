<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('payments', function (Blueprint $table) {
            // Default 'approved' so every already-posted historical payment stays counted as-is.
            $table->string('status')->default('approved')->after('is_backdated');
            $table->string('attachment_path')->nullable()->after('reference');
            $table->foreignId('approved_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });
    }
    public function down(): void {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'attachment_path', 'approved_at', 'rejection_reason']);
        });
    }
};