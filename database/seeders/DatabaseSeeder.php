<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ── Roles ────────────────────────────────────────────────────────────

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);
        $salesAgentRole = Role::firstOrCreate(['name' => 'sales_agent']);
        $vendorAgentRole = Role::firstOrCreate(['name' => 'vendor_agent']);

        // ── Permissions: module.action ──────────────────────────────────────

        $modules = [
            'team',              // users / team management
            'customers',
            'vehicles',
            'bid_sheets',
            'bids',
            'results',           // bidding result (won/lost)
            'costings',
            'invoices',
            'payments',
            'vendor_payments',
            'expenses',
            'shipments',
            'documents',
            'accounting',
        ];

        $actions = ['index', 'create', 'edit', 'delete', 'print'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "$module.$action"]);
            }
        }

        // Report permissions (view/export only, no CRUD)
        foreach (['agent_wise', 'vendor_wise', 'bid_wise', 'bid_won'] as $report) {
            Permission::firstOrCreate(['name' => "reports.$report"]);
        }

        // ── Sync role → permissions ─────────────────────────────────────────

        // Super Admin: everything.
        $superAdminRole->syncPermissions(Permission::all());

        // Accountant: full accounting + money modules, view-only on sales pipeline.
        $accountantModules = ['costings', 'invoices', 'payments', 'vendor_payments', 'expenses', 'shipments', 'documents', 'accounting'];
        $accountantPermissions = [];
        foreach ($accountantModules as $module) {
            foreach ($actions as $action) {
                $accountantPermissions[] = "$module.$action";
            }
        }
        foreach (['customers', 'vehicles', 'bid_sheets', 'results'] as $module) {
            $accountantPermissions[] = "$module.index";
            $accountantPermissions[] = "$module.print";
        }
        $accountantPermissions = array_merge($accountantPermissions, [
            'reports.agent_wise', 'reports.vendor_wise', 'reports.bid_wise', 'reports.bid_won',
        ]);
        $accountantRole->syncPermissions(Permission::whereIn('name', $accountantPermissions)->get());

        // Sales Agent: own customers/vehicles/bidding pipeline, view invoices/documents.
        $salesAgentModules = ['customers', 'vehicles', 'bid_sheets'];
        $salesAgentPermissions = [];
        foreach ($salesAgentModules as $module) {
            foreach ($actions as $action) {
                $salesAgentPermissions[] = "$module.$action";
            }
        }
        foreach (['results', 'costings', 'invoices', 'documents', 'shipments'] as $module) {
            $salesAgentPermissions[] = "$module.index";
            $salesAgentPermissions[] = "$module.print";
        }
        $salesAgentPermissions[] = 'reports.agent_wise';
        $salesAgentRole->syncPermissions(Permission::whereIn('name', $salesAgentPermissions)->get());

        // Vendor Agent: view-only on vehicles assigned to them.
        $vendorAgentRole->syncPermissions(
            Permission::whereIn('name', ['vehicles.index', 'vehicles.print', 'reports.vendor_wise'])->get()
        );

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
            [
                'name'     => 'Super Admin',
                'email'    => 'admin@bidding.test',
                'password' => Hash::make('12345678'),
                'status'   => 'active',
            ]
        );
        $admin->syncRoles($superAdminRole);

        $accountant = User::firstOrCreate(
            ['username' => 'accountant'],
            [
                'name'     => 'Accounts Team',
                'email'    => 'accountant@bidding.test',
                'password' => Hash::make('12345678'),
                'status'   => 'active',
                'created_by' => $admin->id,
            ]
        );
        $accountant->syncRoles($accountantRole);

        $salesAgent = User::firstOrCreate(
            ['username' => 's.khan'],
            [
                'name'     => 'Sales Agent - Khan',
                'email'    => 'skhan@bidding.test',
                'password' => Hash::make('12345678'),
                'status'   => 'active',
                'sales_commission_percent' => 15.00,
                'sales_fixed_bonus'        => 5000,
                'created_by' => $admin->id,
            ]
        );
        $salesAgent->syncRoles($salesAgentRole);

        $vendorAgent = User::firstOrCreate(
            ['username' => 'v.tanaka'],
            [
                'name'     => 'Vendor Agent - Tanaka',
                'email'    => 'tanaka@bidding.test',
                'password' => Hash::make('12345678'),
                'status'   => 'active',
                'vendor_commission_percent' => 7.00,
                'vendor_location'           => 'USS Tokyo, Japan',
                'created_by' => $admin->id,
            ]
        );
        $vendorAgent->syncRoles($vendorAgentRole);
    }
}