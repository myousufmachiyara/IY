<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Models\{ChartOfAccount, Customer, Invoice, Port, User, Vendor};
use App\Support\ReportCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Shared plumbing for every V2 report:
 *  - one generic screen / Excel / PDF renderer driven by column + filter definitions
 *    (filters, sorting and totals carry through to both exports),
 *  - own-record scoping for sales agents,
 *  - hiding of confidential columns unless the user holds finance.view_sensitive.
 *
 * Column definition keys:
 *   key, label, type (text|money|int|pct|date|badge|bool), link (row key holding a URL),
 *   total (bool — sum in footer), sensitive (bool), class (extra td class)
 */
abstract class BaseReportController extends Controller
{
    public const WON_STATUSES = ['won', 'invoiced', 'dispatched', 'arrived', 'delivered'];

    // ───────────────────────────── rendering ─────────────────────────────

    protected function render(Request $request, string $key, array $columns, Collection $rows, array $filters = [], array $opts = [])
    {
        $report  = ReportCatalog::find($key) ?? ['label' => Str::headline($key), 'category' => null, 'description' => null];
        $columns = $this->visibleColumns($columns);
        $rows    = $rows->values();

        $totals = $opts['totals'] ?? [];
        foreach ($columns as $c) {
            if (($c['total'] ?? false) && ! array_key_exists($c['key'], $totals)) {
                $totalRows = isset($opts['total_rows']) ? $opts['total_rows'] : $rows;
                $totals[$c['key']] = $totalRows->sum(fn ($r) => (float) ($r[$c['key']] ?? 0));
            }
        }
        $hasTotals = $rows->isNotEmpty() && collect($columns)->contains(fn ($c) => array_key_exists($c['key'], $totals));

        $filterSummary = $this->filterSummary($request, $filters);
        $title = $report['label'];

        if ($request->export === 'excel') {
            $data = $rows->map(fn ($r) => array_map(fn ($c) => $this->exportValue($r[$c['key']] ?? null, $c), $columns))->all();
            if ($hasTotals) {
                $data[] = array_map(fn ($i) => $i === 0 ? 'Total' : (array_key_exists($columns[$i]['key'], $totals) ? $this->exportValue($totals[$columns[$i]['key']], $columns[$i]) : ''), array_keys($columns));
            }
            $headings = array_map(fn ($c) => ($c['type'] ?? 'text') === 'money' ? $c['label'] . ' (¥)' : $c['label'], $columns);
            return Excel::download(new ArrayExport($data, $headings), Str::slug($title) . '-' . now()->format('Ymd') . '.xlsx');
        }

        $view = [
            'reportKey'     => $key,
            'report'        => $report,
            'title'         => $title,
            'columns'       => $columns,
            'rows'          => $rows,
            'totals'        => $totals,
            'hasTotals'     => $hasTotals,
            'filters'       => $filters,
            'filterSummary' => $filterSummary,
            'cards'         => $opts['cards'] ?? [],
            'notes'         => (array) ($opts['notes'] ?? []),
            'chart'         => $opts['chart'] ?? null,
            'empty'         => $opts['empty'] ?? 'No records match the selected filters.',
            'sections'      => $opts['sections'] ?? [],
        ];

        if ($request->export === 'pdf') {
            return Pdf::loadView('reports.v2.pdf', $view)
                ->setPaper('a4', count($columns) > 7 ? 'landscape' : 'portrait')
                ->download(Str::slug($title) . '-' . now()->format('Ymd') . '.pdf');
        }

        return view($opts['view'] ?? 'reports.v2.table', $view);
    }

    protected function visibleColumns(array $columns): array
    {
        $canSensitive = $this->canSensitive();
        return array_values(array_filter($columns, fn ($c) => $canSensitive || ! ($c['sensitive'] ?? false)));
    }

    protected function exportValue($value, array $col)
    {
        $type = $col['type'] ?? 'text';
        if ($value === null || $value === '') {
            return '';
        }
        return match ($type) {
            'money', 'int' => (int) round((float) $value),
            'pct'          => round((float) $value, 1),
            'bool'         => $value ? 'Yes' : 'No',
            'date'         => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value,
            default        => is_array($value) ? ($value['text'] ?? '') : strip_tags((string) $value),
        };
    }

    protected function filterSummary(Request $request, array $filters): array
    {
        $out = [];
        foreach ($filters as $f) {
            switch ($f['type']) {
                case 'date_range':
                    $from = $request->input($f['from'] ?? 'from', $f['default_from'] ?? null);
                    $to   = $request->input($f['to'] ?? 'to', $f['default_to'] ?? null);
                    if ($from || $to) {
                        $out[$f['label']] = ($from ?: '…') . ' → ' . ($to ?: '…');
                    }
                    break;
                case 'select':
                    $v = $request->input($f['name'], $f['default'] ?? null);
                    if ($v !== null && $v !== '') {
                        $out[$f['label']] = $f['options'][$v] ?? $v;
                    }
                    break;
                default:
                    $v = $request->input($f['name'], $f['default'] ?? null);
                    if ($v !== null && $v !== '') {
                        $out[$f['label']] = $v;
                    }
            }
        }
        return $out;
    }

    // ───────────────────────────── permissions / scope ─────────────────────────────

    protected function canSensitive(): bool
    {
        return (bool) auth()->user()?->can(ReportCatalog::SENSITIVE_PERMISSION);
    }

    protected function viewsAll(): bool
    {
        return (bool) auth()->user()?->can('data.view_all');
    }

    /** True when this user must only see their own records (mirrors App\Models\Scopes\AgentScope). */
    protected function ownOnly(): bool
    {
        $u = auth()->user();
        return $u && ! $u->can('data.view_all') && $u->can('scope.by_agent');
    }

    /** Agent id to filter by: forced to self for own-record users, otherwise the chosen filter. */
    protected function agentId(Request $request): ?int
    {
        if ($this->ownOnly()) {
            return (int) auth()->id();
        }
        return $request->filled('agent_id') ? (int) $request->agent_id : null;
    }

    /**
     * Everyone who sells: holders of scope.by_agent. Admins that hold every permission
     * (incl. data.view_all) are left out unless they actually own customers.
     */
    protected function agentUsers(): Collection
    {
        static $cache = null;
        return $cache ??= User::permission('scope.by_agent')->orderBy('name')->get()
            ->reject(fn (User $u) => $u->can('data.view_all') && ! Customer::allAgents()->where('agent_id', $u->id)->exists())
            ->values();
    }

    /** Sales agents to report on, respecting own-record scope and the agent filter. */
    protected function agents(Request $request): Collection
    {
        $id = $this->agentId($request);
        if ($id && ! $this->agentUsers()->contains('id', $id)) {
            return User::whereKey($id)->get();
        }
        return $this->agentUsers()->when($id, fn ($c) => $c->where('id', $id))->values();
    }

    // ───────────────────────────── filter builders ─────────────────────────────

    protected function fDateRange(string $label = 'Date range', string $from = 'from', string $to = 'to', ?string $defaultFrom = null, ?string $defaultTo = null): array
    {
        return ['type' => 'date_range', 'label' => $label, 'from' => $from, 'to' => $to, 'default_from' => $defaultFrom, 'default_to' => $defaultTo];
    }

    protected function fDate(string $name, string $label, ?string $default = null): array
    {
        return ['type' => 'date', 'name' => $name, 'label' => $label, 'default' => $default];
    }

    protected function fNumber(string $name, string $label, $default = null): array
    {
        return ['type' => 'number', 'name' => $name, 'label' => $label, 'default' => $default];
    }

    protected function fText(string $name, string $label): array
    {
        return ['type' => 'text', 'name' => $name, 'label' => $label];
    }

    protected function fSelect(string $name, string $label, array $options, $default = null, string $all = 'All'): array
    {
        return ['type' => 'select', 'name' => $name, 'label' => $label, 'options' => $options, 'default' => $default, 'all' => $all];
    }

    /** Agent dropdown — omitted entirely for own-record users. */
    protected function fAgent(): ?array
    {
        if ($this->ownOnly()) {
            return null;
        }
        return $this->fSelect('agent_id', 'Agent', $this->agentUsers()->pluck('name', 'id')->all());
    }

    protected function fCustomer(string $all = 'All'): array
    {
        return $this->fSelect('customer_id', 'Customer', Customer::orderBy('name')->pluck('name', 'id')->all(), null, $all);
    }

    protected function fVendor(): array
    {
        return $this->fSelect('vendor_id', 'Vendor', Vendor::orderBy('name')->pluck('name', 'id')->all());
    }

    protected function fCountry(string $label = 'Country'): array
    {
        $countries = Customer::whereNotNull('country')->where('country', '!=', '')->distinct()->orderBy('country')->pluck('country')->all();
        return $this->fSelect('country', $label, array_combine($countries, $countries) ?: []);
    }

    protected function fPort(): array
    {
        return $this->fSelect('port_id', 'Port', Port::orderBy('name')->pluck('name', 'id')->all());
    }

    protected function fMoneyAccount(string $name = 'account_id', string $label = 'Account'): array
    {
        return $this->fSelect($name, $label, ChartOfAccount::moneyAccounts()->mapWithKeys(fn ($a) => [$a->id => "{$a->code} — {$a->name}"])->all());
    }

    protected function fGranularity(): array
    {
        return $this->fSelect('granularity', 'Period granularity', ['month' => 'Monthly', 'quarter' => 'Quarterly'], 'month', '');
    }

    /** Drops nulls (e.g. the agent filter for agents). */
    protected function filters(?array ...$defs): array
    {
        return array_values(array_filter($defs));
    }

    // ───────────────────────────── query helpers ─────────────────────────────

    protected function range($query, string $column, ?string $from, ?string $to)
    {
        return $query
            ->when($from, fn ($q) => $q->whereDate($column, '>=', $from))
            ->when($to, fn ($q) => $q->whereDate($column, '<=', $to));
    }

    /** Sale (non-deposit), non-cancelled invoices. */
    protected function saleInvoices()
    {
        return Invoice::query()
            ->where(fn ($q) => $q->where('invoice_type', '!=', 'deposit')->orWhereNull('invoice_type'))
            ->where('status', '!=', 'cancelled');
    }

    protected function asOf(Request $request): string
    {
        return $request->input('as_of') ?: today()->toDateString();
    }

    protected function pct($num, $den): float
    {
        return $den ? round($num / $den * 100, 1) : 0.0;
    }

    protected function dueDate(Invoice $i): ?Carbon
    {
        return $i->due_final ?? $i->due_first ?? ($i->issued_at ? $i->issued_at->copy() : null);
    }

    /**
     * Period buckets between two dates. Returns [key => ['label','from','to']].
     */
    protected function periods(string $from, string $to, string $granularity = 'month'): array
    {
        $start = Carbon::parse($from)->startOfMonth();
        if ($granularity === 'quarter') {
            $start = $start->firstOfQuarter();
        }
        $end = Carbon::parse($to);
        $out = [];
        while ($start->lte($end)) {
            $pEnd = $granularity === 'quarter' ? $start->copy()->lastOfQuarter() : $start->copy()->endOfMonth();
            $key = $granularity === 'quarter' ? $start->year . '-Q' . $start->quarter : $start->format('Y-m');
            $label = $granularity === 'quarter' ? 'Q' . $start->quarter . ' ' . $start->year : $start->format('M Y');
            $out[$key] = ['label' => $label, 'from' => $start->toDateString(), 'to' => $pEnd->toDateString()];
            $start = $pEnd->copy()->addDay()->startOfDay();
        }
        return $out;
    }

    protected function periodKey($date, string $granularity = 'month'): ?string
    {
        if (! $date) {
            return null;
        }
        $d = $date instanceof Carbon ? $date : Carbon::parse($date);
        return $granularity === 'quarter' ? $d->year . '-Q' . $d->quarter : $d->format('Y-m');
    }

    protected function periodLabel($date, string $granularity = 'month'): string
    {
        $d = $date instanceof Carbon ? $date : Carbon::parse($date);
        return $granularity === 'quarter' ? 'Q' . $d->quarter . ' ' . $d->year : $d->format('M Y');
    }

    /** URL only if the current user may open it — keeps drill-downs from leading to 403 pages. */
    protected function link(?string $permission, string $route, $params = []): ?string
    {
        if ($permission && ! auth()->user()?->can($permission)) {
            return null;
        }
        return route($route, $params);
    }

    protected function reportLink(string $key, array $params = []): ?string
    {
        $r = ReportCatalog::find($key);
        return $r ? $this->link($r['permission'], $r['route'], $params) : null;
    }

    protected function badge(string $text, string $variant = 'secondary'): array
    {
        return ['text' => $text, 'variant' => $variant];
    }

    protected function statusBadge(?string $status): array
    {
        $variant = match ($status) {
            'paid', 'arrived', 'delivered', 'approved', 'complete' => 'success',
            'partial', 'dispatched', 'pending', 'pending_approval', 'awaiting_payment' => 'warning',
            'cancelled', 'rejected', 'lost' => 'danger',
            'issued', 'invoiced', 'won' => 'primary',
            default => 'secondary',
        };
        return $this->badge(Str::headline((string) $status ?: '—'), $variant);
    }
}
