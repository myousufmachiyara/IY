<?php

namespace Database\Seeders;

use App\Models\{User, Vendor, Port, ChartOfAccount};
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

        // Clean up permissions from modules that have since been renamed —
        // prevents duplicate/orphaned rows in the Roles & Permissions UI, and
        // ensures nothing keeps quietly checking a name no route uses anymore.
        // team→members, user_roles→roles, vehicles→vehicle_requirement,
        // bids→merge_bids, results→bid_results.
        foreach (['team', 'user_roles', 'vehicles', 'bids', 'results'] as $oldModule) {
            Permission::where('name', 'like', "{$oldModule}.%")->delete();
        }

        // ── Permissions: module.action ──────────────────────────────────────

        $modules = [
            'members', 'roles', 'customers', 'vehicle_requirement', 'vendors',
            'bid_sheets', 'merge_bids', 'bid_results', 'invoices', 'shipments',
            'payments', 'vendor_payments', 'expenses', 'pending_approvals',
            'accounting', 'costings', 'documents',
        ];

        $actions = ['index', 'show', 'create', 'edit', 'delete', 'print'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "$module.$action"]);
            }
        }
        foreach (['agent_wise', 'vendor_wise', 'bid_wise', 'bid_won', 'customer_wise'] as $report) {
            Permission::firstOrCreate(['name' => "reports.$report"]);
        }

        foreach (['data.view_all', 'scope.by_agent', 'finance.backdate', 'customers.assign_any_agent', 'system.logs', 'invoices.request', 'dates.future'] as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // ── Sync role → permissions ─────────────────────────────────────────

        $superAdminRole->syncPermissions(Permission::all());

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
            ['code' => '5700', 'name' => 'Rent Expense',                'type' => 'expense'],
            ['code' => '5800', 'name' => 'Utilities Expense',           'type' => 'expense'],
            ['code' => '5950', 'name' => 'Bank Charges',                'type' => 'expense'],
        ];

        foreach ($coaData as $item) {
            ChartOfAccount::firstOrCreate(
                ['code' => $item['code']],
                array_merge($item, ['is_system' => true, 'is_active' => true])
            );
        }

        // ── Ports (destination ports for vehicle export) ────────────────────

        $ports = [
            'Mombasa, Kenya', 'Dar es Salaam, Tanzania', 'Durban, South Africa',
            'Port Qasim, Pakistan', 'Karachi, Pakistan', 'Colombo, Sri Lanka',
            'Chattogram, Bangladesh', 'Apapa (Lagos), Nigeria', 'Tin Can Island, Nigeria',
            'Cotonou, Benin', 'Jebel Ali, UAE', 'Suva, Fiji', 'Auckland, New Zealand',
            'Port Louis, Mauritius', 'Georgetown, Guyana',
        ];
        foreach ($ports as $name) {
            Port::firstOrCreate(['name' => $name]);
        }

        // ── Users (login is via username) ───────────────────────────────────

        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            ['name' => 'Super Admin', 'email' => 'admin@bidding.test', 'password' => Hash::make('12345678'), 'status' => 'active']
        );
        $admin->syncRoles($superAdminRole);

        // ── Vendors (no login — just business records) ──────────────────────

        Vendor::firstOrCreate(
            ['name' => 'Vendor - Tanaka'],
            ['location' => 'USS Tokyo, Japan', 'commission_percent' => 7.00, 'status' => 'active', 'created_by' => $admin->id]
        );
    }
}