<?php

namespace Modules\ReportManagement\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exports any report the registry can build. The report has already resolved
 * its rows to scalars keyed by column, so this only has to project the visible
 * columns in order — which is what makes the column picker apply to downloads
 * as well as the screen.
 */
class TabularReportExport implements FromArray, ShouldAutoSize, WithCustomCsvSettings, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, array<string, mixed>>  $columns  visible column descriptors
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totals
     */
    public function __construct(
        private readonly string $title,
        private readonly array $columns,
        private readonly array $rows,
        private readonly array $totals = [],
        private readonly string $totalsLabel = 'Total',
    ) {}

    public function headings(): array
    {
        return array_map(
            fn (array $column) => $column['labelNp'] ?? $column['label'],
            $this->columns,
        );
    }

    public function array(): array
    {
        $keys = array_column($this->columns, 'key');

        $rows = array_map(
            fn (array $row) => array_map(fn (string $key) => $row[$key] ?? '', $keys),
            $this->rows,
        );

        if ($this->totals !== []) {
            // Label the footer under the first column so the row reads as a
            // total rather than as another shareholder.
            $rows[] = array_map(
                fn (string $key, int $index) => $index === 0 ? $this->totalsLabel : ($this->totals[$key] ?? ''),
                $keys,
                array_keys($keys),
            );
        }

        return $rows;
    }

    /**
     * Bold headings, and wrapped text so the composite cells that carry a
     * newline (name over address, citizenship over district) show both lines
     * rather than one long run.
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle($sheet->calculateWorksheetDimension())
            ->getAlignment()->setWrapText(true);

        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        // Excel forbids : \ / ? * [ ] in sheet names and caps them at 31 chars.
        return mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', ' ', $this->title), 0, 31);
    }

    public function getCsvSettings(): array
    {
        // Without the BOM, Excel opens a Devanagari CSV as mojibake.
        return ['use_bom' => true];
    }
}
