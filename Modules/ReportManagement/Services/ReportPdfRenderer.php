<?php

namespace Modules\ReportManagement\Services;

use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * PDF rendering for the reports, on mPDF rather than DomPDF.
 *
 * The share register is filed in Nepali, and DomPDF cannot typeset Devanagari:
 * it has no OpenType layout engine, so it draws glyphs in the order they are
 * stored. Devanagari needs the opposite — conjuncts have to be substituted
 * (ल + ् + ल → ल्ल) and a ि matra typed after its consonant has to be moved in
 * front of it. Without that, शेयरधनिको comes out as visible nonsense, which is
 * worse than a blank page because it looks plausible.
 *
 * mPDF applies those OpenType features itself (`useOTL`), and FreeSerif — which
 * ships with mPDF — covers Devanagari, so no font has to be vendored in.
 */
class ReportPdfRenderer
{
    /**
     * Bundled with mPDF, covers Devanagari and Latin, and is a serif face,
     * which suits a filed statutory register.
     */
    public const FONT = 'freeserif';

    /**
     * Renders a Blade view to a PDF download.
     *
     * @param  array<string, mixed>  $data
     * @param  string  $orientation  'L' for landscape, 'P' for portrait
     */
    public function download(string $view, array $data, string $filename, string $orientation = 'L'): Response
    {
        $pdf = $this->make($orientation);

        $pdf->WriteHTML(view($view, $data)->render());

        return new Response($pdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->safeFilename($filename).'"',
        ]);
    }

    protected function make(string $orientation): Mpdf
    {
        $tempDir = storage_path('app/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => $orientation === 'P' ? 'A4' : 'A4-L',
            // The OpenType layout pass. Without it the Devanagari columns are
            // wrong rather than merely ugly.
            'useOTL' => 0xFF,
            'default_font' => self::FONT,
            'default_font_size' => 9,
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'tempDir' => $tempDir,
        ]);
    }

    /**
     * Keeps quotes and newlines out of the Content-Disposition header.
     */
    protected function safeFilename(string $filename): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $filename);
    }
}
