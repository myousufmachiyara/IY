<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AccountMapping extends Model
{
    /**
     * Every posting role the software uses. Posting code asks for a role (key), never a
     * hard-coded account code, so any account can be edited, replaced or deleted as long
     * as the role is re-pointed first.
     *
     * natures: head natures an account must belong to.   money: must be a cash/bank account.
     */
    public const KEYS = [
        'default_money_account' => ['label' => 'Default cash / bank account', 'group' => 'Money',
            'help' => 'Pre-selected in every payment, receipt and expense form.', 'natures' => ['asset'], 'money' => true, 'default' => '1010'],

        'invoice_credit' => ['label' => 'Sale invoice — credit side', 'group' => 'Sales',
            'help' => 'Credited when a sale invoice is issued (the debit goes to the customer). Default is Unearned Sales Revenue, so income is NOT touched until money is received. Point it at Sales Income if you want standard accrual accounting.',
            'natures' => ['liability', 'income'], 'default' => '2200'],
        'sales_income' => ['label' => 'Sales income (recognised on receipt)', 'group' => 'Sales',
            'help' => 'Credited as customer money is received against sale invoices.', 'natures' => ['income'], 'default' => '4000'],
        'customer_deposits' => ['label' => 'Customer security deposits', 'group' => 'Sales',
            'help' => 'Liability credited by deposit invoices; debited when a deposit is applied to an invoice.', 'natures' => ['liability'], 'default' => '2100'],

        'vehicle_cost' => ['label' => 'Vehicle purchase cost', 'group' => 'Vehicle costing',
            'help' => 'Buying price of a won vehicle.', 'natures' => ['expense'], 'default' => '5000'],
        'vendor_commission_cost' => ['label' => 'Vendor commission', 'group' => 'Vehicle costing',
            'help' => 'Vendor commission from the costing screen.', 'natures' => ['expense'], 'default' => '5400'],
        'inland_cost' => ['label' => 'Inland charges', 'group' => 'Vehicle costing',
            'help' => 'Inland charges from the costing screen.', 'natures' => ['expense'], 'default' => '5200'],
        'auction_cost' => ['label' => 'Auction platform commission', 'group' => 'Vehicle costing',
            'help' => 'Auction platform commission from the costing screen.', 'natures' => ['expense'], 'default' => '5300'],
        'freight_cost' => ['label' => 'Freight & shipping', 'group' => 'Vehicle costing',
            'help' => 'Freight from the costing screen / shipment split.', 'natures' => ['expense'], 'default' => '5100'],
        'misc_cost' => ['label' => 'Miscellaneous vehicle expenses', 'group' => 'Vehicle costing',
            'help' => 'Misc expenses from the costing screen.', 'natures' => ['expense'], 'default' => '5900'],

        'expense_salary' => ['label' => 'Expense category: Salary', 'group' => 'Operating expenses', 'natures' => ['expense'], 'default' => '5500'],
        'expense_office' => ['label' => 'Expense category: Office', 'group' => 'Operating expenses', 'natures' => ['expense'], 'default' => '5600'],
        'expense_utilities' => ['label' => 'Expense category: Utilities', 'group' => 'Operating expenses', 'natures' => ['expense'], 'default' => '5800'],
        'expense_rent' => ['label' => 'Expense category: Rent', 'group' => 'Operating expenses', 'natures' => ['expense'], 'default' => '5700'],
        'expense_bank_charges' => ['label' => 'Expense category: Bank charges', 'group' => 'Operating expenses', 'natures' => ['expense'], 'default' => '5950'],
        'expense_misc' => ['label' => 'Expense category: Miscellaneous', 'group' => 'Operating expenses', 'natures' => ['expense'], 'default' => '5900'],
    ];

    protected $fillable = ['key', 'account_id'];

    private static ?array $cache = null;

    public function account(): BelongsTo { return $this->belongsTo(ChartOfAccount::class, 'account_id'); }

    public static function accountFor(string $key): ChartOfAccount
    {
        self::$cache ??= static::pluck('account_id', 'key')->all();

        $id = self::$cache[$key] ?? null;
        $account = $id ? ChartOfAccount::find($id) : null;

        if (! $account) {
            $label = self::KEYS[$key]['label'] ?? $key;
            throw new RuntimeException("Accounting role \"{$label}\" is not mapped to an account. Open Accounting → Account Mapping and select one.");
        }

        return $account;
    }

    public static function flush(): void { self::$cache = null; }
}
