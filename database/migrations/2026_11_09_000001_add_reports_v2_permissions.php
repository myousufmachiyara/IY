<?php

use App\Support\ReportCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates a reports.{key} permission for every report in ReportCatalog plus the
 * "View Confidential Report Fields" business permission, so a deploy only needs
 * `php artisan migrate` (no re-seed) for the new reports to appear on the Role screen.
 *
 * Grants: Super Admin gets everything. Roles that already see all records
 * (data.view_all) get the confidential-fields permission so their existing
 * reports keep showing buy prices/costs exactly as before. New report access
 * for every other role is left for an admin to tick on the Role screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = array_merge(ReportCatalog::permissionNames(), [ReportCatalog::SENSITIVE_PERMISSION]);
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        if ($super = Role::where('name', 'super_admin')->first()) {
            $super->givePermissionTo($names);
        }

        if (Permission::where('name', 'data.view_all')->exists()) {
            Role::permission('data.view_all')->get()
                ->each(fn ($role) => $role->givePermissionTo(ReportCatalog::SENSITIVE_PERMISSION));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Only drop permissions that did not exist before this migration.
        $legacy = [
            'agent_wise', 'vendor_wise', 'bid_wise', 'bid_won', 'customer_wise', 'agent_profitability',
            'auction_house_performance', 'vehicle_profitability', 'customer_profitability',
            'shipment', 'receivables', 'payables', 'financial_reports',
        ];
        $new = array_diff(ReportCatalog::permissionNames(), array_map(fn ($k) => "reports.$k", $legacy));
        Permission::whereIn('name', array_merge($new, [ReportCatalog::SENSITIVE_PERMISSION]))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
