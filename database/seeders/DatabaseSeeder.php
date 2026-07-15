<?php

namespace Database\Seeders;

use App\Models\{User, Vendor, ChartOfAccount};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles ────────────────────────────────────────────────────────────

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);
        $salesAgentRole = Role::firstOrCreate(['name' => 'sales_agent']);

        // Vendors no longer log in — remove any leftover vendor role/permission.
        Role::where('name', 'vendor_agent')->delete();
        Permission::where('name', 'scope.by_vendor')->delete();

        // ── Permissions: module.action ──────────────────────────────────────

        $modules = [
            'team', 'user_roles', 'customers', 'vehicles', 'bid_sheets', 'bids', 'results',
            'costings', 'invoices', 'payments', 'vendor_payments', 'expenses',
            'shipments', 'documents', 'accounting', 'vendors',
        ];
        $actions = ['index', 'show', 'create', 'edit', 'delete', 'print'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "$module.$action"]);
            }
        }

        foreach (['agent_wise', 'vendor_wise', 'bid_wise', 'bid_won'] as $report) {
            Permission::firstOrCreate(['name' => "reports.$report"]);
        }

        foreach (['data.view_all', 'scope.by_agent', 'finance.backdate', 'customers.assign_any_agent'] as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // ── Sync role → permissions ─────────────────────────────────────────

        $superAdminRole->syncPermissions(Permission::all());

        $accountantPermissions = [];
        foreach (['costings', 'invoices', 'payments', 'vendor_payments', 'expenses', 'shipments', 'documents', 'accounting'] as $module) {
            foreach ($actions as $action) {
                $accountantPermissions[] = "$module.$action";
            }
        }
        foreach (['customers', 'vehicles', 'bid_sheets', 'vendors'] as $module) {
            $accountantPermissions[] = "$module.index";
            $accountantPermissions[] = "$module.show";
            $accountantPermissions[] = "$module.print";
        }
        $accountantPermissions[] = 'results.index';
        $accountantPermissions[] = 'results.show';
        $accountantPermissions[] = 'results.edit';
        $accountantPermissions[] = 'results.print';
        $accountantPermissions[] = 'vehicles.edit';
        $accountantPermissions = array_merge($accountantPermissions, [
            'reports.agent_wise', 'reports.vendor_wise', 'reports.bid_wise', 'reports.bid_won',
            'data.view_all', 'finance.backdate', 'customers.assign_any_agent',
        ]);
        $accountantRole->syncPermissions(Permission::whereIn('name', array_unique($accountantPermissions))->get());

        $salesAgentPermissions = [];
        foreach (['customers', 'vehicles', 'bid_sheets'] as $module) {
            foreach ($actions as $action) {
                $salesAgentPermissions[] = "$module.$action";
            }
        }
        foreach (['results', 'invoices', 'documents', 'payments'] as $module) {
            $salesAgentPermissions[] = "$module.index";
            $salesAgentPermissions[] = "$module.show";
            $salesAgentPermissions[] = "$module.print";
        }
        $salesAgentPermissions[] = 'costings.index';
        $salesAgentPermissions[] = 'costings.show';
        $salesAgentPermissions[] = 'costings.edit';
        $salesAgentPermissions[] = 'costings.print';
        $salesAgentPermissions[] = 'shipments.index';
        $salesAgentPermissions[] = 'shipments.show';
        $salesAgentPermissions[] = 'shipments.create';
        $salesAgentPermissions[] = 'shipments.print';
        $salesAgentPermissions[] = 'vendors.index'; // view-only — for context when picking a vendor at bid-won time
        $salesAgentPermissions[] = 'reports.agent_wise';
        $salesAgentRole->syncPermissions(Permission::whereIn('name', array_unique($salesAgentPermissions))->get());

        // ── Chart of Accounts (system accounts) ─────────────────────────────

        $coaData = [
            ['code' => '1000', 'name' => 'Cash',                        'type' => 'asset'],
            ['code' => '1010', 'name' => 'Bank',                        'type' => 'asset'],
            ['code' => '1100', 'name' => 'Accounts Receivable',         'type' => 'asset'],
            ['code' => '2000', 'name' => 'Accounts Payable — Vendors',  'type' => 'liability'],
            ['code' => '2100', 'name' => 'Customer Security Deposits',  'type' => 'liability'],
            ['code' => '3000', 'name' => "Owner's Equity",              'type' => 'equity'],
            ['code' => '4000', 'name' => 'Vehicle Sales Income',        'type' => 'income'],
            ['code' => '5000', 'name' => 'Cost of Vehicles',            'type' => 'expense'],
            ['code' => '5100', 'name' => 'Freight & Shipping',          'type' => 'expense'],
            ['code' => '5200', 'name' => 'Inland Charges',              'type' => 'expense'],
            ['code' => '5300', 'name' => 'Auction Commission',          'type' => 'expense'],
            ['code' => '5400', 'name' => 'Vendor Commission',           'type' => 'expense'],
            ['code' => '5500', 'name' => 'Salaries',                    'type' => 'expense'],
            ['code' => '5600', 'name' => 'Office Expenses',             'type' => 'expense'],
            ['code' => '5900', 'name' => 'Miscellaneous Expenses',      'type' => 'expense'],
        ];

        foreach ($coaData as $item) {
            ChartOfAccount::firstOrCreate(
                ['code' => $item['code']],
                array_merge($item, ['is_system' => true, 'is_active' => true])
            );
        }

        // ── Users (login is via username) ───────────────────────────────────

        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            ['name' => 'Super Admin', 'email' => 'admin@bidding.test', 'password' => Hash::make('12345678'), 'status' => 'active']
        );
        $admin->syncRoles($superAdminRole);

        $accountant = User::firstOrCreate(
            ['username' => 'accountant'],
            ['name' => 'Accounts Team', 'email' => 'accountant@bidding.test', 'password' => Hash::make('12345678'), 'status' => 'active', 'created_by' => $admin->id]
        );
        $accountant->syncRoles($accountantRole);

        $salesAgent = User::firstOrCreate(
            ['username' => 's.khan'],
            [
                'name' => 'Sales Agent - Khan', 'email' => 'skhan@bidding.test', 'password' => Hash::make('12345678'), 'status' => 'active',
                'sales_commission_percent' => 15.00, 'sales_fixed_bonus' => 5000, 'created_by' => $admin->id,
            ]
        );
        $salesAgent->syncRoles($salesAgentRole);

        // ── Vendors (no login — just business records) ──────────────────────

        Vendor::firstOrCreate(
            ['name' => 'Vendor - Tanaka'],
            ['location' => 'USS Tokyo, Japan', 'commission_percent' => 7.00, 'status' => 'active', 'created_by' => $admin->id]
        );
    }
}