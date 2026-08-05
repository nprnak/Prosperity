<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /* freeserif covers Devanagari as well as Latin; mPDF shapes it with
           useOTL, so the Nepali register typesets correctly. */
        body { font-family: freeserif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .org { font-size: 11px; font-weight: bold; margin: 0; }
        .meta { color: #6b7280; margin: 6px 0 12px; font-size: 8px; }
        /* overflow:wrap makes mPDF wrap an over-wide table onto more lines
           instead of shrink-to-fit, which squeezes character spacing until
           separators like ", " disappear. Registers run to eleven columns, so
           this matters. */
        table.data { width: 100%; border-collapse: collapse; overflow: wrap; }
        table.data th { background: #f3f4f6; text-align: left; padding: 4px 5px; border: 1px solid #d1d5db; font-size: 8px; }
        table.data td { padding: 3px 5px; border: 1px solid #e5e7eb; word-wrap: break-word; }
        table.data td.right, table.data th.right { text-align: right; }
        table.data tfoot td { font-weight: bold; background: #f9fafb; }
    </style>
</head>
<body>
    <p class="org">{{ $orgName }}</p>
    <p class="org" style="font-weight: normal;">{{ $orgAddress }}</p>

    <h1>{{ $title }}</h1>

    <p class="meta">
        Generated {{ $generatedAt }} · {{ number_format(count($rows)) }} row(s)
        @if (count($appliedFilters))
            · Filters:
            @foreach ($appliedFilters as $label => $value)
                {{ $label }} = {{ $value }}@if (! $loop->last), @endif
            @endforeach
        @endif
    </p>

    <table class="data">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th class="{{ ($column['align'] ?? 'left') === 'right' ? 'right' : '' }}">
                        {{ $column['labelNp'] ?? $column['label'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $column)
                        {{-- Composite cells separate their parts with a newline;
                             see ShareLagatReport::LINE. --}}
                        <td class="{{ ($column['align'] ?? 'left') === 'right' ? 'right' : '' }}">
                            {!! nl2br(e($row[$column['key']] ?? '')) !!}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">No rows match the selected filters.</td></tr>
            @endforelse
        </tbody>
        @if (count($totals))
            <tfoot>
                <tr>
                    @foreach ($columns as $index => $column)
                        <td class="{{ ($column['align'] ?? 'left') === 'right' ? 'right' : '' }}">
                            {{ $index === 0 ? $totalsLabel : ($totals[$column['key']] ?? '') }}
                        </td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
