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
        // ── Roles ────────────────────────────────────────────────────────────

        $superAdminRole  = Role::firstOrCreate(['name' => 'super_admin']);
        $accountantRole  = Role::firstOrCreate(['name' => 'accountant']);
        $salesAgentRole  = Role::firstOrCreate(['name' => 'sales_agent']);
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

        // 'show' is required so every resource detail page (vehicle, customer,
        // invoice, bid sheet...) has a matching permission for CheckModulePermission
        // to check — without it, GET .../{id} routes resolve to an unseeded permission.
        $actions = ['index', 'show', 'create', 'edit', 'delete', 'print'];

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

        // ---- Accountant ----------------------------------------------------
        // Full control over money/back-office modules; view-only on the sales
        // pipeline; CAN record bidding results (won/lost) since that's the
        // trigger for their downstream costing/invoicing work.
        $accountantPermissions = [];

        $accountantFullModules = [
            'costings', 'invoices', 'payments', 'vendor_payments',
            'expenses', 'shipments', 'documents', 'accounting',
        ];
        foreach ($accountantFullModules as $module) {
            foreach ($actions as $action) {
                $accountantPermissions[] = "$module.$action";
            }
        }

        foreach (['customers', 'vehicles', 'bid_sheets'] as $module) {
            $accountantPermissions[] = "$module.index";
            $accountantPermissions[] = "$module.show";
            $accountantPermissions[] = "$module.print";
        }

        // Results: view + mark won/lost, but not "full CRUD" (no create/delete of results themselves).
        $accountantPermissions[] = 'results.index';
        $accountantPermissions[] = 'results.show';
        $accountantPermissions[] = 'results.edit';
        $accountantPermissions[] = 'results.print';

        $accountantPermissions = array_merge($accountantPermissions, [
            'reports.agent_wise', 'reports.vendor_wise', 'reports.bid_wise', 'reports.bid_won',
        ]);

        $accountantRole->syncPermissions(
            Permission::whereIn('name', array_unique($accountantPermissions))->get()
        );

        // ---- Sales Agent -----------------------------------------------------
        // Full control over their own customers/vehicles/bidding pipeline;
        // view-only on results/invoices/documents (accountant/admin owns those
        // decisions); costings.edit is granted narrowly so they can set their
        // OWN selling price — the cost-breakdown fields stay blocked at the
        // controller level (canBackdate()) regardless of this permission;
        // shipments.create lets them start a dispatch grouping for their
        // customer, but schedule/dispatch/arrive (shipments.edit) stay
        // super admin/accountant only.
        $salesAgentPermissions = [];

        $salesAgentFullModules = ['customers', 'vehicles', 'bid_sheets'];
        foreach ($salesAgentFullModules as $module) {
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

        $salesAgentPermissions[] = 'reports.agent_wise';

        $salesAgentRole->syncPermissions(
            Permission::whereIn('name', array_unique($salesAgentPermissions))->get()
        );

        // ---- Vendor Agent ------------------------------------------------
        // View-only on vehicles assigned to them.
        $vendorAgentRole->syncPermissions(
            Permission::whereIn('name', ['vehicles.index', 'vehicles.show', 'vehicles.print', 'reports.vendor_wise'])->get()
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
                'name'       => 'Accounts Team',
                'email'      => 'accountant@bidding.test',
                'password'   => Hash::make('12345678'),
                'status'     => 'active',
                'created_by' => $admin->id,
            ]
        );
        $accountant->syncRoles($accountantRole);

        $salesAgent = User::firstOrCreate(
            ['username' => 's.khan'],
            [
                'name'                     => 'Sales Agent - Khan',
                'email'                    => 'skhan@bidding.test',
                'password'                 => Hash::make('12345678'),
                'status'                   => 'active',
                'sales_commission_percent' => 15.00,
                'sales_fixed_bonus'        => 5000,
                'created_by'               => $admin->id,
            ]
        );
        $salesAgent->syncRoles($salesAgentRole);

        $vendorAgent = User::firstOrCreate(
            ['username' => 'v.tanaka'],
            [
                'name'                      => 'Vendor Agent - Tanaka',
                'email'                     => 'tanaka@bidding.test',
                'password'                  => Hash::make('12345678'),
                'status'                    => 'active',
                'vendor_commission_percent' => 7.00,
                'vendor_location'           => 'USS Tokyo, Japan',
                'created_by'                => $admin->id,
            ]
        );
        $vendorAgent->syncRoles($vendorAgentRole);
    }
}