<?php

namespace App\Services;

use App\Models\{AccountHead, AccountMapping, AccountSubhead, ChartOfAccount};
use Illuminate\Support\Facades\Schema;

/**
 * Builds and maintains the Head → Sub-head → Account structure. install() is idempotent:
 * safe to run from the migration, the seeder and the accounting:restate command.
 */
class AccountStructure
{
    public const HEADS = [
        ['code' => '1', 'name' => 'Assets',      'nature' => 'asset',     'sort_order' => 1],
        ['code' => '2', 'name' => 'Liabilities', 'nature' => 'liability', 'sort_order' => 2],
        ['code' => '3', 'name' => 'Equity',      'nature' => 'equity',    'sort_order' => 3],
        ['code' => '4', 'name' => 'Income',      'nature' => 'income',    'sort_order' => 4],
        ['code' => '5', 'name' => 'Expenses',    'nature' => 'expense',   'sort_order' => 5],
    ];

    public const SUBHEADS = [
        ['code' => '11', 'head' => '1', 'name' => 'Cash in Hand',                    'kind' => 'cash'],
        ['code' => '12', 'head' => '1', 'name' => 'Bank Accounts',                   'kind' => 'bank'],
        ['code' => '13', 'head' => '1', 'name' => 'Trade Receivables — Customers',   'kind' => 'customer'],
        ['code' => '14', 'head' => '1', 'name' => 'Other Current Assets',            'kind' => null],
        ['code' => '21', 'head' => '2', 'name' => 'Trade Payables — Vendors',        'kind' => 'vendor'],
        ['code' => '22', 'head' => '2', 'name' => 'Customer Deposits & Unearned Revenue', 'kind' => null],
        ['code' => '23', 'head' => '2', 'name' => 'Other Liabilities',               'kind' => null],
        ['code' => '31', 'head' => '3', 'name' => 'Capital & Reserves',              'kind' => null],
        ['code' => '41', 'head' => '4', 'name' => 'Sales Income',                    'kind' => null],
        ['code' => '42', 'head' => '4', 'name' => 'Other Income',                    'kind' => null],
        ['code' => '51', 'head' => '5', 'name' => 'Direct Vehicle Costs',            'kind' => null],
        ['code' => '52', 'head' => '5', 'name' => 'Operating Expenses',              'kind' => null],
    ];

    /** Existing system account code → sub-head code. */
    public const CLASSIFY = [
        '1000' => '11', '1010' => '12', '1100' => '13',
        '2000' => '21', '2100' => '22', '2200' => '22',
        '3000' => '31', '4000' => '41',
        '5000' => '51', '5100' => '51', '5200' => '51', '5300' => '51', '5400' => '51',
        '5500' => '52', '5600' => '52', '5700' => '52', '5800' => '52', '5900' => '52', '5950' => '52',
    ];

    /** System accounts the default mapping points at. Created only if missing AND their role is still unmapped. */
    public const DEFAULT_ACCOUNTS = [
        '1000' => 'Cash',                                   '1010' => 'Bank',
        '2100' => 'Customer Security Deposits',             '2200' => 'Unearned Sales Revenue',
        '4000' => 'Vehicle Sales Income',
        '5000' => 'Cost of Vehicles',                       '5100' => 'Freight & Shipping',
        '5200' => 'Inland Charges',                         '5300' => 'Auction Platform Commission Charges',
        '5400' => 'Vendor Commission',                      '5500' => 'Salaries',
        '5600' => 'Office Expenses',                        '5700' => 'Rent Expense',
        '5800' => 'Utilities Expense',                      '5900' => 'Miscellaneous Expenses',
        '5950' => 'Bank Charges',
    ];

    /** Where an unclassified account of each nature lands. */
    private const FALLBACK = ['asset' => '14', 'liability' => '23', 'equity' => '31', 'income' => '42', 'expense' => '52'];

    public static function install(): void
    {
        if (! Schema::hasTable('account_heads') || ! Schema::hasColumn('chart_of_accounts', 'subhead_id')) {
            return;
        }

        $heads = [];
        foreach (self::HEADS as $h) {
            $heads[$h['code']] = AccountHead::firstOrCreate(['code' => $h['code']], $h);
        }

        $subs = [];
        foreach (self::SUBHEADS as $s) {
            $subs[$s['code']] = AccountSubhead::firstOrCreate(['code' => $s['code']], [
                'head_id'    => $heads[$s['head']]->id,
                'name'       => $s['name'],
                'kind'       => $s['kind'],
                'sort_order' => (int) $s['code'],
            ]);
        }

        ChartOfAccount::whereNull('subhead_id')->get()->each(function (ChartOfAccount $a) use ($subs) {
            if ($a->customer_id || $a->type === 'customer') {
                $sub = $subs['13'];
            } elseif ($a->vendor_id || $a->type === 'vendor') {
                $sub = $subs['21'];
            } elseif (isset(self::CLASSIFY[$a->code])) {
                $sub = $subs[self::CLASSIFY[$a->code]];
            } else {
                $sub = $subs[self::FALLBACK[$a->type] ?? '14'];
            }

            $a->update(['subhead_id' => $sub->id, 'type' => $sub->head->nature]);
        });

        foreach (AccountMapping::KEYS as $key => $meta) {
            if (AccountMapping::where('key', $key)->exists()) {
                continue;
            }

            $account = ChartOfAccount::where('code', $meta['default'])->first();

            // A default account that never existed on this database is created — but only while its
            // role is unmapped, so a deliberate later deletion is never undone by re-running install().
            if (! $account && isset(self::DEFAULT_ACCOUNTS[$meta['default']], self::CLASSIFY[$meta['default']])) {
                $sub = $subs[self::CLASSIFY[$meta['default']]];
                $account = ChartOfAccount::create([
                    'code' => $meta['default'], 'account_code' => $meta['default'],
                    'name' => self::DEFAULT_ACCOUNTS[$meta['default']],
                    'type' => $sub->head->nature, 'subhead_id' => $sub->id,
                    'is_system' => true, 'is_active' => true,
                ]);
            }

            if (! $account && ! empty($meta['money'])) {
                $account = ChartOfAccount::whereHas('subhead', fn ($q) => $q->whereIn('kind', ['cash', 'bank']))->orderBy('code')->first();
            }

            if ($account) {
                AccountMapping::create(['key' => $key, 'account_id' => $account->id]);
            }
        }

        AccountMapping::flush();
    }
}
