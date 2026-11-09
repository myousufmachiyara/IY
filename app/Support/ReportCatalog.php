<?php

namespace App\Support;

use App\Models\User;

/**
 * Single source of truth for every report in the app (Billtrix Reports V2).
 *
 * Drives: permission names (reports.{key}), the Role screen's "Report Access"
 * accordion, the sidebar menu, the Reports hub page and the per-category tabs
 * on each report. Add a report here + a route and it shows up everywhere.
 *
 * Keys of reports that existed before V2 (agent_wise, customer_wise, bid_won,
 * shipment, customer_profitability, vehicle_profitability, …) are kept so the
 * permissions already ticked on existing roles keep working.
 */
class ReportCatalog
{
    /** Business permission that unlocks confidential columns (buy price, costs, bank accounts…) inside reports. */
    public const SENSITIVE_PERMISSION = 'finance.view_sensitive';

    public const CATEGORIES = [
        'executive' => [
            'label' => 'Executive & Management',
            'icon'  => 'fa-briefcase',
            'reports' => [
                'executive_summary' => ['label' => 'Executive Business Summary', 'route' => 'reports.executive_summary', 'priority' => 'P1',
                    'description' => 'Purchases, invoiced sales, approved collections, gross/company profit, receivables, payables and cash.'],
                'business_trends' => ['label' => 'Business Performance Trends', 'route' => 'reports.business_trends', 'priority' => 'P3',
                    'description' => 'Monthly / quarterly growth across sales, purchases, collections and profit.'],
                'business_exposure' => ['label' => 'Business Exposure Report', 'route' => 'reports.business_exposure', 'priority' => 'P3',
                    'description' => 'Cash position against receivables, vendor payables, deposits held and unposted vehicle costs.'],
            ],
        ],
        'sales' => [
            'label' => 'Sales & Customers',
            'icon'  => 'fa-users',
            'reports' => [
                'customer_wise' => ['label' => 'Customer-wise Sales', 'route' => 'reports.customer_wise', 'priority' => 'P1',
                    'description' => 'Sales, approved payments and outstanding balances by customer.'],
                'customer_360' => ['label' => 'Customer 360°', 'route' => 'reports.customer_360', 'priority' => 'P1',
                    'description' => 'Full operational and financial history of one customer.'],
                'customer_profitability' => ['label' => 'Customer Profitability', 'route' => 'reports.customer_profitability', 'priority' => 'P2',
                    'description' => 'Sales value, cost, profit and margin by customer.'],
                'customer_purchase_history' => ['label' => 'Customer Purchase History', 'route' => 'reports.customer_purchase_history', 'priority' => 'P2',
                    'description' => 'Every vehicle a customer has won, with invoice and shipment status.'],
                'invoice_summary' => ['label' => 'Invoice Summary', 'route' => 'reports.invoice_summary', 'priority' => 'P1',
                    'description' => 'Invoice statuses, payable, paid and outstanding amounts with due dates.'],
                'inactive_customers' => ['label' => 'Inactive Customers', 'route' => 'reports.inactive_customers', 'priority' => 'P3',
                    'description' => 'Customers with no bid, purchase or payment in the chosen number of days.'],
            ],
        ],
        'vehicles' => [
            'label' => 'Auctions & Vehicles',
            'icon'  => 'fa-car',
            'reports' => [
                'bid_won' => ['label' => 'Won Vehicles', 'route' => 'reports.bid_won', 'priority' => 'P1',
                    'description' => 'All successfully won lots, whatever their current status.'],
                'vehicle_pipeline' => ['label' => 'Vehicle Pipeline', 'route' => 'reports.vehicle_pipeline', 'priority' => 'P1',
                    'description' => 'Stage-by-stage progress of every vehicle with days in stage.'],
                'vehicle_profitability' => ['label' => 'Vehicle Profitability', 'route' => 'reports.vehicle_profitability', 'priority' => 'P1',
                    'description' => 'Unit economics per vehicle: every cost line, sale, profit and margin.'],
            ],
        ],
        'finance' => [
            'label' => 'Finance & Payments',
            'icon'  => 'fa-coins',
            'reports' => [
                'receivables_aging' => ['label' => 'Customer Receivables & Aging', 'route' => 'reports.receivables_aging', 'priority' => 'P1',
                    'description' => 'Outstanding invoice balances bucketed by days overdue.'],
                'payment_collections' => ['label' => 'Customer Payment Collections', 'route' => 'reports.payment_collections', 'priority' => 'P1',
                    'description' => 'Customer payment register — approved, pending and rejected.'],
                'payment_stages' => ['label' => 'Payment Stage Report', 'route' => 'reports.payment_stages', 'priority' => 'P2',
                    'description' => 'Deposit, 50% shipping and 100% release gates per vehicle.'],
                'security_deposits' => ['label' => 'Security Deposit Report', 'route' => 'reports.security_deposits', 'priority' => 'P2',
                    'description' => 'Deposits invoiced, collected, applied and still held.'],
                'vendor_payables' => ['label' => 'Vendor Payables', 'route' => 'reports.vendor_payables', 'priority' => 'P1',
                    'description' => 'Costed vendor payable less vendor payments, per vehicle.'],
                'vendor_payment_history' => ['label' => 'Vendor Payment History', 'route' => 'reports.vendor_payment_history', 'priority' => 'P2',
                    'description' => 'Register of every vendor remittance, with reversal status.'],
                'expense_analysis' => ['label' => 'Expense Analysis', 'route' => 'reports.expense_analysis', 'priority' => 'P2',
                    'description' => 'Operating expenses by category and period.'],
                'revenue_vs_expense' => ['label' => 'Revenue vs Expense Analysis', 'route' => 'reports.revenue_vs_expense', 'priority' => 'P3',
                    'description' => 'Recognised revenue against vehicle costs and operating expenses per period.'],
            ],
        ],
        'shipping' => [
            'label' => 'Shipping & Logistics',
            'icon'  => 'fa-ship',
            'reports' => [
                'shipment' => ['label' => 'Shipment Overview', 'route' => 'reports.shipment', 'priority' => 'P2',
                    'description' => 'Shipment master with vehicles, dates and status.'],
                'ready_for_shipment' => ['label' => 'Ready for Shipment', 'route' => 'reports.ready_for_shipment', 'priority' => 'P2',
                    'description' => 'Invoiced vehicles that have passed the 50% dispatch gate.'],
                'awaiting_shipment' => ['label' => 'Awaiting Shipment & Yard Aging', 'route' => 'reports.awaiting_shipment', 'priority' => 'P2',
                    'description' => 'Won vehicles not yet dispatched, days waiting and blocker.'],
                'shipping_cost' => ['label' => 'Shipping Cost Analysis', 'route' => 'reports.shipping_cost', 'priority' => 'P3',
                    'description' => 'Freight totals, per-vehicle freight and trend vs average.'],
            ],
        ],
        'agents' => [
            'label' => 'Agents & Commissions',
            'icon'  => 'fa-user-tie',
            'reports' => [
                'agent_wise' => ['label' => 'Agent Performance', 'route' => 'reports.agent_wise', 'priority' => 'P1',
                    'description' => 'Bids, wins, win rate, invoiced sales, profit and earnings per agent.'],
                'agent_commission' => ['label' => 'Agent Commission', 'route' => 'reports.agent_commission', 'priority' => 'P2',
                    'description' => 'Commission accrual per vehicle: floor, upsell, %, bonus, earned.'],
                'agent_monthly' => ['label' => 'Agent Monthly Comparison', 'route' => 'reports.agent_monthly', 'priority' => 'P2',
                    'description' => 'Month-by-month agent trend.'],
                'agent_conversion' => ['label' => 'Agent Sales Conversion', 'route' => 'reports.agent_conversion', 'priority' => 'P3',
                    'description' => 'Bid → win → invoice → paid funnel per agent.'],
                'agent_portfolio' => ['label' => 'Agent Customer Portfolio', 'route' => 'reports.agent_portfolio', 'priority' => 'P2',
                    'description' => 'Customer base, active customers, sales and receivables per agent.'],
                'agent_leaderboard' => ['label' => 'Agent Leaderboard', 'route' => 'reports.agent_leaderboard', 'priority' => 'P3',
                    'description' => 'Company-wide ranking by wins, sales, collections or commission.'],
            ],
        ],
        'other' => [
            'label' => 'Other Reports',
            'icon'  => 'fa-folder-open',
            'reports' => [
                'bid_wise' => ['label' => 'Bid Activity by Date', 'route' => 'reports.bid_wise', 'priority' => null,
                    'description' => 'Bids placed and won per auction date.'],

                'auction_house_performance' => ['label' => 'Auction House Performance', 'route' => 'reports.auction_house_performance', 'priority' => null,
                    'description' => 'Won vehicles, average price and profit by auction house / vendor.'],

                'vendor_wise' => ['label' => 'Vendor Summary', 'route' => 'reports.vendor_wise', 'priority' => null,
                    'description' => 'Vehicles, payable and paid totals per vendor.'],

                'receivables' => ['label' => 'Receivables Ledger', 'route' => 'accounting.receivables', 'priority' => null,
                    'description' => 'Customer balances straight from the ledger (Accounting).'],

                'payables' => ['label' => 'Payables Ledger', 'route' => 'accounting.payables', 'priority' => null,
                    'description' => 'Vendor balances straight from the ledger (Accounting).'],

                'financial_reports' => ['label' => 'Financial Statements', 'route' => 'accounting.profit_loss', 'priority' => null,
                    'description' => 'Trial balance, balance sheet, profit & loss, cash flow (Accounting).'],

                'agent_profitability' => ['label' => 'Agent Profitability', 'route' => 'reports.agent_profitability', 'priority' => null,
                    'description' => 'Gross profit, agent earning and company profit per agent.'],
            ],
        ],
    ];

    /** Every report keyed by report key, with its category attached. */
    public static function all(): array
    {
        $out = [];
        foreach (self::CATEGORIES as $catKey => $cat) {
            foreach ($cat['reports'] as $key => $r) {
                $out[$key] = $r + ['key' => $key, 'category' => $catKey, 'permission' => "reports.$key"];
            }
        }
        return $out;
    }

    /** First report of a category this user may open (category landing page). */
    public static function firstFor(?User $user, string $category): ?array
    {
        $cat = self::visibleTo($user)[$category] ?? null;
        return $cat ? reset($cat['reports']) : null;
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function permissionNames(): array
    {
        return array_map(fn ($r) => $r['permission'], array_values(self::all()));
    }

    /** Categories filtered to the reports this user may open (empty categories dropped). */
    public static function visibleTo(?User $user): array
    {
        if (! $user) {
            return [];
        }
        $out = [];
        foreach (self::CATEGORIES as $catKey => $cat) {
            $reports = [];
            foreach ($cat['reports'] as $key => $r) {
                if ($user->can("reports.$key")) {
                    $reports[$key] = $r + ['key' => $key];
                }
            }
            if ($reports) {
                $out[$catKey] = ['label' => $cat['label'], 'icon' => $cat['icon'], 'reports' => $reports];
            }
        }
        return $out;
    }

    public static function userCanAny(?User $user): bool
    {
        return (bool) self::visibleTo($user);
    }
}
