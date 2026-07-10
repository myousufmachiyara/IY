// database/migrations/2026_01_02_000014_create_documents_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('type')->nullable();   // invoice | bill_of_lading | export_cert | inspection ...
            $table->string('title');
            $table->string('file_path');
            $table->boolean('is_final_clearance')->default(false); // released only at 100% paid
            $table->boolean('visible_to_customer')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('documents'); }
};