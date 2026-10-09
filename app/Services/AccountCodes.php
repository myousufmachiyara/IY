<?php

namespace App\Services;

use App\Models\{AccountHead, AccountSubhead, ChartOfAccount};

/**
 * Generates Head, Sub-head and Account codes so nobody types them by hand.
 *
 *   Head      next free whole number                      1, 2, 3, 4, 5, 6 …
 *   Sub-head  head code + running number                  head 1 → 11, 12 … 19, 110, 111 …
 *   Account   4-digit number inside the head's range,     asset 1xxx, liability 2xxx, equity 3xxx,
 *             stepping by 10 so there is room to insert   income 4xxx, expense 5xxx
 *
 * Customer / vendor accounts (CUS-4, VEN-2) are not 4-digit and are ignored when counting.
 * The next*() functions are pure (they take the codes already used), so they can be unit-tested
 * without a database; the for*() wrappers read the used codes from the tables.
 */
class AccountCodes
{
    public const NATURE_BASE = [
        'asset' => 1000, 'liability' => 2000, 'equity' => 3000, 'income' => 4000, 'expense' => 5000,
    ];

    public const STEP = 10;

    // ───────────────────────────── pure ─────────────────────────────

    /** @param string[] $existing every head code in use */
    public static function nextHeadCode(array $existing): string
    {
        $used = array_flip(array_map('strval', $existing));
        $n = 0;
        foreach ($existing as $c) {
            if (ctype_digit((string) $c)) {
                $n = max($n, (int) $c);
            }
        }
        do { $n++; } while (isset($used[(string) $n]));

        return (string) $n;
    }

    /**
     * @param string[] $headSubheadCodes codes of the sub-heads already inside this head
     * @param string[] $allSubheadCodes  every sub-head code in the system (codes are globally unique)
     */
    public static function nextSubheadCode(string $headCode, array $headSubheadCodes, array $allSubheadCodes): string
    {
        $used = array_flip(array_map('strval', $allSubheadCodes));
        $n = 0;
        foreach ($headSubheadCodes as $c) {
            $c = (string) $c;
            if (str_starts_with($c, $headCode) && ctype_digit($suffix = substr($c, strlen($headCode))) && $suffix !== '') {
                $n = max($n, (int) $suffix);
            }
        }
        do { $n++; $code = $headCode . $n; } while (isset($used[$code]));

        return $code;
    }

    /** @param string[] $existing every account code in use */
    public static function nextAccountCode(string $nature, array $existing): string
    {
        $base = self::NATURE_BASE[$nature] ?? self::NATURE_BASE['asset'];
        $top  = $base + 999;
        $used = array_flip(array_map('strval', $existing));

        $max = null;
        foreach ($existing as $c) {
            $c = (string) $c;
            if (strlen($c) === 4 && ctype_digit($c) && (int) $c >= $base && (int) $c <= $top) {
                $max = max($max ?? 0, (int) $c);
            }
        }

        $n = $max === null ? $base : $max + self::STEP;
        while (isset($used[(string) $n]) && $n <= $top) { $n++; }

        // Range full (100 accounts at step 10 is plenty, but never fail): fall back to any free slot.
        if ($n > $top) {
            for ($n = $base; $n <= $top; $n++) {
                if (! isset($used[(string) $n])) { return (string) $n; }
            }
            throw new \RuntimeException("No free account codes left in the {$nature} range.");
        }

        return (string) $n;
    }

    // ───────────────────────────── database ─────────────────────────────

    public static function forHead(): string
    {
        return self::nextHeadCode(AccountHead::pluck('code')->all());
    }

    public static function forSubhead(AccountHead $head): string
    {
        return self::nextSubheadCode(
            $head->code,
            AccountSubhead::where('head_id', $head->id)->pluck('code')->all(),
            AccountSubhead::pluck('code')->all(),
        );
    }

    public static function forAccount(string $nature): string
    {
        return self::nextAccountCode($nature, ChartOfAccount::pluck('code')->all());
    }
}
