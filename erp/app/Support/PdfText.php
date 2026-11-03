<?php

namespace App\Support;

/**
 * Text that must stay on ONE line in a dompdf table cell. dompdf has no
 * text-overflow, and a line that overflows its cell is broken onto another
 * line instead of clipped — so a row grows. Each text is measured with
 * dompdf's own font metrics (DejaVu Sans, the only face the PDFs use) and cut,
 * with "…", to the column it sits in. Used by the emailed statement
 * (statement-table/v19-email) and the sales invoice (pdf-sales-invoice).
 */
class PdfText
{
    private $metrics;
    private array $face;

    /** @param float $pageWidth printable width in pt (A4 portrait less the side margins) */
    public function __construct(private float $pageWidth)
    {
        $this->metrics = (new \Dompdf\Dompdf())->getFontMetrics();
        $this->face = [false => $this->metrics->getFont('DejaVu Sans', 'normal'), true => $this->metrics->getFont('DejaVu Sans', 'bold')];
    }

    /** A4 portrait less $sideMm margins each side. */
    public static function a4(float $sideMm): self
    {
        return new self(595.28 - 2 * $sideMm * 72 / 25.4);
    }

    /** Width in pt of $s at $px CSS pixels (dompdf: 96 dpi, 1px = 0.75pt). */
    public function width(?string $s, float $px, bool $bold = false): float
    {
        return (float) $this->metrics->getTextWidth((string) $s, $this->face[$bold], $px * .75);
    }

    /**
     * $s cut to fit a column of $pct % of the page, less its 6px padding each
     * side and $reserve pt for whatever shares the line. ৳ has no glyph in
     * DejaVu Sans, so it is written "Tk".
     */
    public function fit(?string $s, float $pct, float $px, float $reserve = 0.0, bool $bold = false): string
    {
        $s = str_replace('৳', 'Tk ', (string) $s);
        $max = $pct / 100 * $this->pageWidth - 9 - 2 - $reserve;
        if ($this->width($s, $px, $bold) <= $max) return $s;
        [$lo, $hi] = [0, mb_strlen($s)];
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi + 1, 2);
            if ($this->width(rtrim(mb_substr($s, 0, $mid)) . '…', $px, $bold) <= $max) $lo = $mid; else $hi = $mid - 1;
        }
        return rtrim(mb_substr($s, 0, $lo)) . '…';
    }
}
