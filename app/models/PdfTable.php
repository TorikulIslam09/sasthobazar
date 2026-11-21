<?php
declare(strict_types=1);

/**
 * Minimal PDF writer for report tables (A4 landscape, Helvetica, no external library).
 * The built-in PDF fonts only cover Latin text (Windows-1252), so reports are exported in English;
 * other characters become "?". For a Bangla PDF use the report's Print button (browser "Save as PDF").
 *
 *   $pdf = new PdfTable('Sales report', '01 Nov 2026 - 21 Nov 2026');
 *   $pdf->cards([['Net sales', 'Tk 12,000.00'], ...]);
 *   $pdf->table('Daily sales', [['Date', 'text'], ['Total', 'num']], [['2026-11-01', '1,200.00'], ...], ['Total', '...']);
 *   $pdf->download('sales.pdf');
 */
final class PdfTable
{
    private const W = 842.0;
    private const H = 595.0;
    private const M = 32.0;
    private const ROW = 14.0;
    private const SIZE = 7.6;
    /** Helvetica widths for chars 32..126 (per 1000 units) */
    private const HW = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556,
        278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667,
        944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500,
        278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];

    private array $pages = [];
    private string $cur = '';
    private float $y = 0;

    public function __construct(private string $title, private string $subtitle = '')
    {
        $this->newPage();
    }

    /** UTF-8 -> Windows-1252 (unknown characters -> ?). */
    public static function enc(string $s): string
    {
        $s = str_replace(['৳', '–', '—', '→', '’', '“', '”'], ['Tk ', '-', '-', '->', "'", '"', '"'], $s);
        $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        if ($out === false) {
            $out = (string) preg_replace('/[^\x20-\x7E]/u', '?', $s);
        }
        return (string) preg_replace('/[\x00-\x1F]/', ' ', $out);
    }

    private static function width(string $s, float $size, bool $bold = false): float
    {
        $w = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $o = ord($s[$i]);
            $w += ($o >= 32 && $o <= 126) ? self::HW[$o - 32] : 556;
        }
        return $w * $size / 1000 * ($bold ? 1.08 : 1.0);
    }

    private static function fit(string $s, float $max, float $size, bool $bold = false): string
    {
        if (self::width($s, $size, $bold) <= $max) {
            return $s;
        }
        while ($s !== '' && self::width($s . '..', $size, $bold) > $max) {
            $s = substr($s, 0, -1);
        }
        return $s . '..';
    }

    private static function esc(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private function text(float $x, float $y, string $s, float $size, bool $bold = false, float $gray = 0.0): void
    {
        $this->cur .= sprintf("BT %.2F g /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $gray, $bold ? 'F2' : 'F1', $size, $x, $y, self::esc($s));
    }

    private function rect(float $x, float $y, float $w, float $h, string $rgb): void
    {
        $this->cur .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", $rgb, $x, $y, $w, $h);
    }

    private function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->cur .= sprintf("0.6 G 0.5 w %.2F %.2F m %.2F %.2F l S\n", $x1, $y1, $x2, $y2);
    }

    private function newPage(): void
    {
        if ($this->cur !== '') {
            $this->pages[] = $this->cur;
        }
        $this->cur = '';
        $top = self::H - self::M;
        $this->text(self::M, $top - 12, self::enc($this->title), 15, true, 0.05);
        if ($this->subtitle !== '') {
            $this->text(self::M, $top - 27, self::enc($this->subtitle), 9, false, 0.35);
        }
        $this->line(self::M, $top - 34, self::W - self::M, $top - 34);
        $this->y = $top - 50;
    }

    private function need(float $h): bool
    {
        if ($this->y - $h < self::M + 18) {
            $this->newPage();
            return true;
        }
        return false;
    }

    /** Summary boxes: [[label, value], ...] */
    public function cards(array $cards): void
    {
        if (!$cards) {
            return;
        }
        $avail = self::W - 2 * self::M;
        $per = min(6, count($cards));
        $w = ($avail - ($per - 1) * 8) / $per;
        foreach (array_chunk($cards, $per) as $chunk) {
            $this->need(40);
            foreach ($chunk as $i => [$label, $value]) {
                $x = self::M + $i * ($w + 8);
                $this->rect($x, $this->y - 32, $w, 34, '0.906 0.957 0.953');
                $this->text($x + 6, $this->y - 10, self::fit(self::enc($label), $w - 12, 7.5), 7.5, false, 0.35);
                $this->text($x + 6, $this->y - 25, self::fit(self::enc($value), $w - 12, 11, true), 11, true, 0.05);
            }
            $this->y -= 44;
        }
    }

    /**
     * @param array $columns [[label, 'text'|'num'], ...]  num = right aligned
     * @param array $rows    already formatted strings
     */
    public function table(string $title, array $columns, array $rows, ?array $totals = null): void
    {
        $avail = self::W - 2 * self::M;
        $widths = [];
        foreach ($columns as $i => [$label]) {
            $max = self::width(self::enc($label), self::SIZE, true);
            foreach (array_slice($rows, 0, 300) as $r) {
                $max = max($max, self::width(self::enc((string) ($r[$i] ?? '')), self::SIZE));
            }
            if ($totals) {
                $max = max($max, self::width(self::enc((string) ($totals[$i] ?? '')), self::SIZE, true));
            }
            $widths[$i] = min(260, max(34, $max + 10));
        }
        $scale = $avail / max(1, array_sum($widths));
        $widths = array_map(static fn($w) => $w * $scale, $widths);

        $header = function () use ($columns, $widths, $avail): void {
            $this->rect(self::M, $this->y - self::ROW + 3, $avail, self::ROW, '0.055 0.463 0.451');
            $x = self::M;
            foreach ($columns as $i => [$label, $type]) {
                $s = self::fit(self::enc($label), $widths[$i] - 8, self::SIZE, true);
                $tx = $type === 'num' ? $x + $widths[$i] - 4 - self::width($s, self::SIZE, true) : $x + 4;
                $this->text($tx, $this->y - 7, $s, self::SIZE, true, 1.0);
                $x += $widths[$i];
            }
            $this->y -= self::ROW;
        };
        $row = function (array $cells, bool $bold, bool $shade) use ($columns, $widths, $avail): void {
            if ($shade) {
                $this->rect(self::M, $this->y - self::ROW + 3, $avail, self::ROW, '0.965 0.973 0.973');
            }
            $x = self::M;
            foreach ($columns as $i => [, $type]) {
                $s = self::fit(self::enc((string) ($cells[$i] ?? '')), $widths[$i] - 8, self::SIZE, $bold);
                $tx = $type === 'num' ? $x + $widths[$i] - 4 - self::width($s, self::SIZE, $bold) : $x + 4;
                $this->text($tx, $this->y - 7, $s, self::SIZE, $bold, 0.1);
                $x += $widths[$i];
            }
            $this->y -= self::ROW;
        };

        $this->need(self::ROW * 4);
        if ($title !== '') {
            $this->text(self::M, $this->y - 4, self::enc($title), 10, true, 0.05);
            $this->y -= 16;
        }
        $header();
        if (!$rows) {
            $row([self::enc('No records')], false, false);
        }
        foreach ($rows as $n => $r) {
            if ($this->need(self::ROW)) {
                $header();
            }
            $row($r, false, $n % 2 === 1);
        }
        if ($totals) {
            $this->need(self::ROW);
            $this->line(self::M, $this->y + 3, self::W - self::M, $this->y + 3);
            $row($totals, true, false);
        }
        $this->y -= 14;
    }

    public function note(string $text): void
    {
        $this->need(14);
        $this->text(self::M, $this->y - 6, self::fit(self::enc($text), self::W - 2 * self::M, 7.5), 7.5, false, 0.4);
        $this->y -= 14;
    }

    public function output(): string
    {
        $pages = array_merge($this->pages, [$this->cur]);
        $n = count($pages);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $stamp = self::enc('Generated ' . date('d M Y, h:i A'));
        foreach ($pages as $i => $content) {
            $content .= sprintf("BT 0.5 g /F1 7 Tf %.2F %.2F Td (%s) Tj ET\n", self::M, self::M - 14, self::esc($stamp));
            $p = self::enc('Page ' . ($i + 1) . ' of ' . $n);
            $content .= sprintf("BT 0.5 g /F1 7 Tf %.2F %.2F Td (%s) Tj ET\n", self::W - self::M - self::width($p, 7), self::M - 14, self::esc($p));
            $pageId = 5 + $i * 2;
            $kids[] = $pageId . ' 0 R';
            $data = function_exists('gzcompress') ? gzcompress($content, 6) : $content;
            $objs[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::W . ' ' . self::H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($pageId + 1) . ' 0 R >>';
            $objs[$pageId + 1] = '<< /Length ' . strlen($data) . (function_exists('gzcompress') ? ' /Filter /FlateDecode' : '') . " >>\nstream\n" . $data . "\nendstream";
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $max = max(array_keys($objs));
        $out .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $out .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $out .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R /Info << /Title (" . self::esc(self::enc($this->title)) . ") /Producer (SasthoBazar) >> >>\nstartxref\n{$xref}\n%%EOF";
        return $out;
    }

    public function download(string $filename): void
    {
        $pdf = $this->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-store');
        echo $pdf;
        exit;
    }
}
