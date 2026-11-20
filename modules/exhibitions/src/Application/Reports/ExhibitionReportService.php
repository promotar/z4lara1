<?php

namespace Modules\Exhibition\Application\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Exhibition\Application\Security\SpreadsheetCellSanitizer;

final class ExhibitionReportService
{
    public const SECTIONS = [
        'artists' => 'exhibitions::ui.report_section_artists',
        'artworks' => 'exhibitions::ui.report_section_artworks',
        'financial' => 'exhibitions::ui.report_section_financial',
        'operations' => 'exhibitions::ui.report_section_operations',
    ];

    public function report(string $section, array $filters): array
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        [$from, $to] = $this->range($filters);
        $currency = strtoupper((string) ($filters['currency'] ?? ''));

        if ($currency !== '' && ! preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = '';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        $result = match ($section) {
            'artists' => $this->artists($from, $to, $status),
            'artworks' => $this->artworks($from, $to, $status),
            'financial' => $this->financial($from, $to, $currency, $status),
            'operations' => $this->operations($from, $to, $status),
        };

        return $result + [
            'section' => $section,
            'title' => __(self::SECTIONS[$section]),
            'from' => $from,
            'to' => $to,
            'currency' => $currency,
            'status' => $status,
            'definitions' => $this->definitions($section),
            'fresh_at' => now()->utc()->toIso8601String(),
            'timezone' => __('exhibitions::ui.report_timezone_note'),
        ];
    }

    public function csv(string $section, array $filters): array
    {
        $report = $this->report($section, $filters);
        $rows = [];

        foreach ($report['metrics'] as $metric) {
            $rows[] = ['metric', $metric['label'], (string) $metric['value'], $metric['unit'] ?? 'count', $metric['source']];
        }

        foreach ($report['rows'] as $row) {
            $rows[] = ['detail', $row['label'], (string) $row['value'], $row['unit'] ?? 'count', $row['source']];
        }

        return array_map(
            [SpreadsheetCellSanitizer::class, 'row'],
            [['row_type', 'label', 'value', 'unit', 'source'], ...$rows],
        );
    }

    private function artists(CarbonImmutable $from, CarbonImmutable $to, string $status): array
    {
        $identity = $this->dated(DB::table('exhibition_artist_identities'), 'created_at', $from, $to);

        if ($status !== '') {
            $identity->where('state', $status);
        }

        $total = (clone $identity)->count();
        $active = (clone $identity)->where('state', 'active')->count();
        $applications = $this->dated(DB::table('exhibition_artist_applications'), 'created_at', $from, $to);
        $accepted = (clone $applications)->whereIn('state', ['approved', 'contract_pending', 'active'])->count();
        $rejected = (clone $applications)->where('state', 'rejected')->count();
        $decided = $accepted + $rejected;
        $artworks = $this->dated(DB::table('exhibition_artworks'), 'created_at', $from, $to)->count();
        $fulfillments = $this->dated(DB::table('exhibition_fulfillments'), 'created_at', $from, $to);
        $sales = (clone $fulfillments)->count();
        $preparation = $this->averageDuration(clone $fulfillments, 'created_at', 'collected_at');
        $delays = (clone $fulfillments)->whereColumn('collected_at', '>', 'preparation_deadline')->count();
        $returns = (clone $fulfillments)->whereIn('state', ['return_requested', 'returned'])->count();
        $disputes = $this->dated(DB::table('exhibition_dispute_cases'), 'created_at', $from, $to)->count();
        $metrics = [
            $this->metric(__('exhibitions::ui.auto_27f6a31432'), $total, 'count', 'exhibition_artist_identities'),
            $this->metric(__('exhibitions::ui.auto_1374cf8801'), $active, 'count', 'exhibition_artist_identities'),
            $this->metric(__('exhibitions::ui.auto_eee6936855'), $decided ? round($accepted / $decided * 100, 2) : 0, 'percent', 'exhibition_artist_applications'),
            $this->metric(__('exhibitions::ui.auto_4162b51f16'), $total ? round($artworks / $total, 2) : 0, 'ratio', 'exhibition_artworks'),
            $this->metric(__('exhibitions::ui.auto_e429c8f4e2'), $total ? round($sales / $total, 2) : 0, 'ratio', 'exhibition_fulfillments'),
            $this->metric(__('exhibitions::ui.auto_54b09ef5e8'), $preparation, 'hours', 'exhibition_fulfillments'),
            $this->metric(__('exhibitions::ui.auto_c7d5b7315e'), $delays, 'count', 'exhibition_fulfillments'),
            $this->metric(__('exhibitions::ui.auto_4762fdc5fd'), $returns, 'count', 'exhibition_fulfillments'),
            $this->metric(__('exhibitions::ui.auto_02abacd550'), $disputes, 'count', 'exhibition_dispute_cases'),
        ];
        $rows = $this->dated(
            DB::table('exhibition_artist_identities as i')
                ->leftJoin('exhibition_artist_applications as a', 'a.core_user_id', '=', 'i.core_user_id'),
            'i.created_at',
            $from,
            $to,
        )
            ->selectRaw("coalesce(a.artistic_name, a.legal_name, 'Artist') label, i.state value")
            ->orderBy('label')
            ->limit(100)
            ->get()
            ->map(fn (object $row): array => [
                'label' => $row->label,
                'value' => $row->value,
                'unit' => 'state',
                'source' => 'exhibition_artist_identities',
            ])
            ->all();

        return compact('metrics', 'rows');
    }

    private function artworks(CarbonImmutable $from, CarbonImmutable $to, string $status): array
    {
        $base = $this->dated(DB::table('exhibition_artworks'), 'created_at', $from, $to);

        if ($status !== '') {
            $base->where('state', $status);
        }

        $count = (clone $base)->count();
        $stateCount = fn (array $states): int => (clone $base)->whereIn('state', $states)->count();
        $review = $this->averageDuration(clone $base, 'submitted_at', 'approved_at');
        $sold = $this->dated(DB::table('exhibition_fulfillments as f'), 'f.created_at', $from, $to);
        $soldCount = (clone $sold)->distinct()->count('f.artwork_id');
        $timeToSale = $this->averageDuration(
            (clone $sold)->join('exhibition_artworks as a', 'a.id', '=', 'f.artwork_id'),
            'a.created_at',
            'f.created_at',
        );
        $metrics = [
            $this->metric(__('exhibitions::ui.auto_1da8fd1cd9'), $count, 'count', 'exhibition_artworks'),
            $this->metric(__('exhibitions::ui.auto_66fc747e4e'), $stateCount(['approved', 'published', 'reserved', 'sold']), 'count', 'exhibition_artworks'),
            $this->metric(__('exhibitions::ui.auto_bb357dc3cb'), $stateCount(['rejected']), 'count', 'exhibition_artworks'),
            $this->metric(__('exhibitions::ui.auto_0fe5025af5'), $stateCount(['published', 'reserved', 'sold']), 'count', 'exhibition_artworks'),
            $this->metric(__('exhibitions::ui.auto_904f69e9e5'), $soldCount, 'count', 'exhibition_fulfillments'),
            $this->metric(__('exhibitions::ui.auto_1fe15d9915'), $review, 'hours', 'exhibition_artworks'),
            $this->metric(__('exhibitions::ui.auto_1fa9d356e9'), $timeToSale, 'hours', 'exhibition_artworks + exhibition_fulfillments'),
        ];
        $rows = (clone $base)
            ->selectRaw("coalesce(category, 'Uncategorized') label, count(*) value")
            ->groupBy('category')
            ->orderByDesc('value')
            ->limit(50)
            ->get()
            ->map(fn (object $row): array => [
                'label' => $row->label,
                'value' => (int) $row->value,
                'unit' => 'artworks',
                'source' => 'exhibition_artworks.category',
            ])
            ->all();

        return compact('metrics', 'rows');
    }

    private function financial(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $currency,
        string $status,
    ): array {
        $base = $this->dated(DB::table('exhibition_settlements'), 'created_at', $from, $to);

        if ($currency !== '') {
            $base->where('currency', $currency);
        }

        if ($status !== '') {
            $base->where('state', $status);
        }

        $metrics = [];

        foreach ((clone $base)->select('currency')->distinct()->pluck('currency') as $code) {
            $query = (clone $base)->where('currency', $code);

            foreach ([
                [__('exhibitions::ui.auto_bd66239aba'), 'sale_price_minor'],
                [__('exhibitions::ui.auto_fc4b130819'), 'platform_commission_minor'],
                [__('exhibitions::ui.auto_8487777ddc'), 'artist_net_minor'],
                [__('exhibitions::ui.auto_53beb26d8b'), 'refund_minor'],
                [__('exhibitions::ui.auto_2b1cf56b44'), 'adjustment_minor'],
            ] as [$label, $column]) {
                $metrics[] = $this->metric(
                    $label,
                    (int) (clone $query)->sum($column),
                    $code.' minor',
                    'exhibition_settlements.'.$column,
                );
            }

            $metrics[] = $this->metric(
                'Pending settlements',
                (clone $query)->whereNotIn('state', ['paid', 'reversed'])->count(),
                'count · '.$code,
                'exhibition_settlements',
            );
            $metrics[] = $this->metric(
                'Completed payouts',
                (clone $query)->where('state', 'paid')->count(),
                'count · '.$code,
                'exhibition_settlements',
            );
        }

        $ledger = $this->dated(DB::table('exhibition_settlement_ledger_entries'), 'occurred_at', $from, $to);

        if ($currency !== '') {
            $ledger->where('currency', $currency);
        }

        $rows = $ledger
            ->selectRaw('entry_type label, sum(amount_minor) value, currency')
            ->groupBy('entry_type', 'currency')
            ->orderBy('entry_type')
            ->get()
            ->map(fn (object $row): array => [
                'label' => $row->label,
                'value' => (int) $row->value,
                'unit' => $row->currency.' minor',
                'source' => 'exhibition_settlement_ledger_entries',
            ])
            ->all();

        return compact('metrics', 'rows');
    }

    private function operations(CarbonImmutable $from, CarbonImmutable $to, string $status): array
    {
        $fulfillments = $this->dated(DB::table('exhibition_fulfillments'), 'created_at', $from, $to);

        if ($status !== '') {
            $fulfillments->where('state', $status);
        }

        $preparation = $this->averageDuration(clone $fulfillments, 'created_at', 'collected_at');
        $shipping = $this->averageDuration(clone $fulfillments, 'shipped_at', 'delivered_at');
        $review = $this->averageDuration(
            $this->dated(DB::table('exhibition_artworks'), 'created_at', $from, $to),
            'submitted_at',
            'approved_at',
        );
        $dispute = $this->averageDuration(
            $this->dated(DB::table('exhibition_dispute_cases'), 'created_at', $from, $to),
            'created_at',
            'decided_at',
        );
        $metrics = [
            $this->metric(__('exhibitions::ui.auto_85991975c3'), $review, 'hours', 'exhibition_artworks'),
            $this->metric(__('exhibitions::ui.auto_76123509be'), $preparation, 'hours', 'exhibition_fulfillments'),
            $this->metric(__('exhibitions::ui.auto_53a2197f10'), $shipping, 'hours', 'exhibition_fulfillments'),
            $this->metric(__('exhibitions::ui.auto_a270cfb42b'), (clone $fulfillments)->whereColumn('collected_at', '>', 'preparation_deadline')->count(), 'count', 'exhibition_fulfillments'),
            $this->metric(__('exhibitions::ui.auto_d1bcadc73d'), (clone $fulfillments)->whereIn('state', ['damaged', 'lost'])->count(), 'count', 'exhibition_fulfillments'),
            $this->metric(__('exhibitions::ui.auto_6a92e00468'), $dispute, 'hours', 'exhibition_dispute_cases'),
        ];
        $rows = (clone $fulfillments)
            ->selectRaw('state label, count(*) value')
            ->groupBy('state')
            ->orderByDesc('value')
            ->get()
            ->map(fn (object $row): array => [
                'label' => $row->label,
                'value' => (int) $row->value,
                'unit' => 'fulfillments',
                'source' => 'exhibition_fulfillments.state',
            ])
            ->all();

        return compact('metrics', 'rows');
    }

    private function definitions(string $section): array
    {
        $common = [
            'timezone' => __('exhibitions::ui.report_note_timezone'),
            'freshness' => __('exhibitions::ui.report_note_freshness'),
            'filters' => __('exhibitions::ui.report_note_filters'),
            'privacy' => __('exhibitions::ui.report_note_privacy'),
        ];
        $specific = [
            'artists' => __('exhibitions::ui.report_note_artists'),
            'artworks' => __('exhibitions::ui.report_note_artworks'),
            'financial' => __('exhibitions::ui.report_note_financial'),
            'operations' => __('exhibitions::ui.report_note_operations'),
        ];

        return $common + ['calculation' => $specific[$section]];
    }

    private function range(array $filters): array
    {
        $timezone = 'Asia/Amman';
        $from = isset($filters['from'])
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['from'], $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->subDays(29)->startOfDay();
        $to = isset($filters['to'])
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['to'], $timezone)->endOfDay()
            : CarbonImmutable::now($timezone)->endOfDay();

        if ($from > $to) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from->utc(), $to->utc()];
    }

    private function dated(
        Builder $query,
        string $column,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): Builder {
        return $query->whereBetween($column, [$from, $to]);
    }

    private function metric(string $label, int|float $value, string $unit, string $source): array
    {
        return compact('label', 'value', 'unit', 'source');
    }

    /**
     * Compute duration in SQL so a report cannot hydrate an unbounded lifecycle
     * history into PHP memory. The expressions cover every database supported by
     * the platform test and production environments.
     */
    private function averageDuration(Builder $query, string $start, string $end): float
    {
        $grammar = $query->getGrammar();
        $startColumn = $grammar->wrap($start);
        $endColumn = $grammar->wrap($end);
        $expression = match ($query->getConnection()->getDriverName()) {
            'sqlite' => "AVG(ABS(strftime('%s', {$endColumn}) - strftime('%s', {$startColumn}))) / 3600.0",
            'pgsql' => "AVG(ABS(EXTRACT(EPOCH FROM ({$endColumn} - {$startColumn})))) / 3600.0",
            'sqlsrv' => "AVG(ABS(DATEDIFF_BIG(SECOND, {$startColumn}, {$endColumn}))) / 3600.0",
            default => "AVG(ABS(TIMESTAMPDIFF(SECOND, {$startColumn}, {$endColumn}))) / 3600.0",
        };
        $row = (clone $query)
            ->whereNotNull($start)
            ->whereNotNull($end)
            ->selectRaw($expression.' as average_hours')
            ->first();

        return round((float) ($row?->average_hours ?? 0), 2);
    }
}
