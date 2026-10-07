<?php

namespace App\Services;

/**
 * Pure arithmetic behind the self-correcting postings. No database access, so every rule
 * here can be tested in isolation.
 */
class AccountingMath
{
    /**
     * Lines needed to bring a vehicle's posted cost in line with its current costing.
     *
     * @param array<int,int> $targetDebits       accountId => total that SHOULD be debited
     * @param array<int,int> $currentNetDebit    accountId => debit minus credit already posted (all accounts touched)
     * @param int            $vendorAccountId    the vendor's payable account
     * @param int            $vendorTargetCredit total that SHOULD be credited to the vendor
     * @return array{lines: array<int,array{account_id:int,debit:int,credit:int}>, debit:int, credit:int}
     */
    public static function costSyncLines(array $targetDebits, array $currentNetDebit, int $vendorAccountId, int $vendorTargetCredit): array
    {
        $lines = [];
        $ids = array_unique(array_merge(array_keys($targetDebits), array_keys($currentNetDebit)));

        foreach ($ids as $id) {
            if ($id === $vendorAccountId) {
                continue;
            }
            $delta = ($targetDebits[$id] ?? 0) - ($currentNetDebit[$id] ?? 0);
            if ($delta > 0) {
                $lines[] = ['account_id' => $id, 'debit' => $delta, 'credit' => 0];
            } elseif ($delta < 0) {
                $lines[] = ['account_id' => $id, 'debit' => 0, 'credit' => -$delta];
            }
        }

        $vendorCurrentCredit = -($currentNetDebit[$vendorAccountId] ?? 0);
        $vDelta = $vendorTargetCredit - $vendorCurrentCredit;
        if ($vDelta > 0) {
            $lines[] = ['account_id' => $vendorAccountId, 'debit' => 0, 'credit' => $vDelta];
        } elseif ($vDelta < 0) {
            $lines[] = ['account_id' => $vendorAccountId, 'debit' => -$vDelta, 'credit' => 0];
        }

        return ['lines' => $lines, 'debit' => array_sum(array_column($lines, 'debit')), 'credit' => array_sum(array_column($lines, 'credit'))];
    }

    /** Positive: recognise more revenue. Negative: take revenue back. */
    public static function recognitionDelta(int $recognised, int $received): int
    {
        return $received - $recognised;
    }

    /** Positive: the receivable must grow. Negative: it must shrink. */
    public static function receivableDelta(int $currentlyPosted, int $totalPayable): int
    {
        return $totalPayable - $currentlyPosted;
    }
}
