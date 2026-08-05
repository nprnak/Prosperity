<?php

namespace Modules\ReportManagement\Reports\Contracts;

/**
 * One report definition.
 *
 * A report describes itself — its filters, its columns and its rows — and the
 * shared controller, Vue page, Excel export and PDF view all render it from
 * that description. Rows come back as plain scalars keyed by column key, so
 * every output format shows the same figures and the column picker works the
 * same way everywhere without per-format mapping.
 *
 * Columns depend on the filters because two of these reports grow a column per
 * share offering in scope.
 */
interface Report
{
    /** URL slug, also the registry key. */
    public function key(): string;

    public function title(): string;

    /** Nepali title, for the reports whose prescribed format is in Nepali. */
    public function titleNp(): ?string;

    /** One line on the report picker explaining what the report answers. */
    public function description(): string;

    /**
     * Filter descriptors the shared UI renders.
     *
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array;

    /**
     * Column descriptors: key, label, optional labelNp/align/type/default.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function columns(array $filters = []): array;

    /**
     * Report body, one array per row keyed by column key.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function rows(array $filters = []): array;

    /**
     * Footer totals keyed by column key. Empty when the report has none.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function totals(array $rows): array;

    /**
     * Label for the totals row. Nepali formats say जम्मा, not "Total".
     */
    public function totalsLabel(): string;

    /**
     * Organisation block to print above the report, when the report is filed by
     * someone other than the operator — the share register belongs to the
     * issuing company, so it carries that company's name. Null uses the
     * configured organisation.
     *
     * @param  array<string, mixed>  $filters
     * @return array{name: string, address: string}|null
     */
    public function heading(array $filters = []): ?array;

    /**
     * True when the report dates itself in Bikram Sambat, as the formats filed
     * in Nepali do.
     */
    public function usesNepaliDates(): bool;
}
