<?php

use App\Services\AccountStructure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('account_heads')) {
            Schema::create('account_heads', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('nature'); // asset | liability | equity | income | expense
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('account_subheads')) {
            Schema::create('account_subheads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('head_id')->constrained('account_heads')->restrictOnDelete();
                $table->string('code')->unique();
                $table->string('name');
                // cash | bank | customer | vendor — drives money dropdowns and party sub-ledgers
                $table->string('kind')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('account_mappings')) {
            Schema::create('account_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('chart_of_accounts', 'subhead_id')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->foreignId('subhead_id')->nullable()->after('type')->constrained('account_subheads')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('customers', 'security_deposit_account_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->foreignId('security_deposit_account_id')->nullable()->after('security_deposit_account')
                    ->constrained('chart_of_accounts')->nullOnDelete();
            });
        }

        // Seeds heads/sub-heads, classifies every existing account, sets default mappings.
        AccountStructure::install();
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'security_deposit_account_id')) {
            Schema::table('customers', fn (Blueprint $t) => $t->dropConstrainedForeignId('security_deposit_account_id'));
        }
        if (Schema::hasColumn('chart_of_accounts', 'subhead_id')) {
            Schema::table('chart_of_accounts', fn (Blueprint $t) => $t->dropConstrainedForeignId('subhead_id'));
        }
        Schema::dropIfExists('account_mappings');
        Schema::dropIfExists('account_subheads');
        Schema::dropIfExists('account_heads');
    }
};
