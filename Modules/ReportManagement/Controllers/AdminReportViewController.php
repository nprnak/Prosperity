<?php

namespace Modules\ReportManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Services\NepaliDateService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Modules\ReportManagement\Exports\TabularReportExport;
use Modules\ReportManagement\Reports\Contracts\Report;
use Modules\ReportManagement\Reports\ReportRegistry;
use Modules\ReportManagement\Services\ReportPdfRenderer;
use Modules\SettingsManagement\Models\Setting;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Serves every prescribed report format from one place.
 *
 * The report describes its own filters and columns, so this controller stays
 * generic: it reads only the filter keys the report declares, resolves which
 * columns are visible, and hands the same rows to the screen, the spreadsheet
 * and the PDF. Adding a report means adding a class to the registry.
 */
class AdminReportViewController extends Controller
{
    public function __construct(
        private ReportRegistry $registry,
        private ReportPdfRenderer $pdf,
        private NepaliDateService $nepaliDates,
    ) {}

    public function show(Request $request, string $report): Response
    {
        $definition = $this->registry->find($report);
        $filters = $this->filters($request, $definition);
        $columns = $definition->columns($filters);
        $rows = $definition->rows($filters);

        return Inertia::render('Admin/ReportView', [
            'report' => [
                'key' => $definition->key(),
                'title' => $definition->title(),
                'titleNp' => $definition->titleNp(),
                'description' => $definition->description(),
            ],
            'reports' => $this->registry->summaries(),
            'filterDefinitions' => $definition->filters(),
            'filters' => $filters,
            'columns' => $columns,
            'visibleColumns' => array_column($this->visibleColumns($request, $columns), 'key'),
            'rows' => $rows,
            'totals' => $definition->totals($rows),
            'totalsLabel' => $definition->totalsLabel(),
            'org' => $this->heading($definition, $filters),
        ]);
    }

    public function export(Request $request, string $report): BinaryFileResponse|SymfonyResponse
    {
        $definition = $this->registry->find($report);
        $filters = $this->filters($request, $definition);
        $columns = $this->visibleColumns($request, $definition->columns($filters));
        $rows = $definition->rows($filters);
        $totals = $definition->totals($rows);

        $format = $request->string('format')->toString() ?: 'xlsx';
        $title = $definition->titleNp() ?: $definition->title();
        $filename = $definition->key().'-'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            $heading = $this->heading($definition, $filters);

            return $this->pdf->download('pdf.tabular-report', [
                'title' => $title,
                'columns' => $columns,
                'rows' => $rows,
                'totals' => $totals,
                'totalsLabel' => $definition->totalsLabel(),
                'appliedFilters' => $this->filterLabels($definition, $filters),
                'orgName' => $heading['name'],
                'orgAddress' => $heading['address'],
                // A register dated in BS should say when it was drawn up in BS too.
                'generatedAt' => $definition->usesNepaliDates()
                    ? $this->nepaliDates->toBikramSambat(now()).' ('.now()->format('H:i').')'
                    : now()->format('Y-m-d H:i'),
            ], $filename.'.pdf');
        }

        $csv = $format === 'csv';

        return Excel::download(
            new TabularReportExport($definition->title(), $columns, $rows, $totals, $definition->totalsLabel()),
            $filename.($csv ? '.csv' : '.xlsx'),
            $csv ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }

    /**
     * The organisation block printed above the report: the report's own where it
     * files under an issuing company, otherwise the configured organisation.
     *
     * @param  array<string, mixed>  $filters
     * @return array{name: string, address: string}
     */
    protected function heading(Report $report, array $filters): array
    {
        return $report->heading($filters) ?? [
            'name' => (string) Setting::get('org_name', config('app.name')),
            'address' => (string) Setting::get('org_address', ''),
        ];
    }

    /**
     * Only the filter keys the report declares are read from the request, so an
     * unrelated query parameter can never reach a report's query.
     *
     * @return array<string, mixed>
     */
    protected function filters(Request $request, Report $report): array
    {
        $filters = [];

        foreach ($report->filters() as $definition) {
            $key = $definition['key'];
            $value = $request->input($key);

            $filters[$key] = match ($definition['type']) {
                'select' => $this->selectValue($definition, $value),
                default => filled($value) ? (string) $value : null,
            };
        }

        return $filters;
    }

    /**
     * Select values are matched against the declared options, so only offered
     * values get through. Numeric option values come back as ints, which is
     * what the report queries compare against.
     *
     * @param  array<string, mixed>  $definition
     */
    protected function selectValue(array $definition, mixed $value): mixed
    {
        if (blank($value)) {
            return null;
        }

        foreach ($definition['options'] ?? [] as $option) {
            if ((string) $option['value'] === (string) $value) {
                return $option['value'];
            }
        }

        return null;
    }

    /**
     * The columns to render: whatever the request asked for, narrowed to what
     * the report actually offers, in the report's own order. Falls back to the
     * columns the format marks as shown by default.
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    protected function visibleColumns(Request $request, array $columns): array
    {
        $requested = array_filter(explode(',', $request->string('columns')->toString()));

        $visible = $requested === []
            ? array_filter($columns, fn (array $column) => $column['default'] ?? true)
            : array_filter($columns, fn (array $column) => in_array($column['key'], $requested, true));

        // Hiding every column would export an empty file; fall back to the
        // report's own default set instead.
        return array_values($visible ?: array_filter($columns, fn (array $column) => $column['default'] ?? true));
    }

    /**
     * Human-readable applied filters for the PDF header, using each filter's
     * own label and the selected option's label rather than raw ids.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    protected function filterLabels(Report $report, array $filters): array
    {
        $labels = [];

        foreach ($report->filters() as $definition) {
            $value = $filters[$definition['key']] ?? null;

            if (blank($value)) {
                continue;
            }

            $option = collect($definition['options'] ?? [])
                ->firstWhere(fn (array $option) => (string) $option['value'] === (string) $value);

            $labels[$definition['label']] = $option['label'] ?? (string) $value;
        }

        return $labels;
    }
}
