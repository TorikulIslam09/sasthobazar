<?php
declare(strict_types=1);

/**
 * Report export: CSV (UTF-8, Excel friendly), Excel (.xlsx, one sheet per table) and PDF (English, see PdfTable).
 *
 * Report array (see Reports):
 *   title, subtitle, cards => [[label, value, type]], tables => [[title, columns => [[key, label, type]], rows, totals]]
 * Column types: text, date, datetime, int, money, num, pct (value is a percentage number, e.g. 12.5)
 */
final class Export
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    /** Plain value for CSV (numbers without grouping). */
    private static function raw(mixed $v, string $type): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        return match ($type) {
            'money', 'num' => number_format((float) $v, 2, '.', ''),
            'int'          => (string) (int) $v,
            'pct'          => number_format((float) $v, 1, '.', ''),
            'date'         => substr((string) $v, 0, 10),
            'datetime'     => substr((string) $v, 0, 16),
            default        => (string) $v,
        };
    }

    /** Display value for PDF (Latin digits). */
    private static function pretty(mixed $v, string $type): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        return match ($type) {
            'money', 'num' => number_format((float) $v, 2),
            'int'          => number_format((float) $v),
            'pct'          => number_format((float) $v, 1) . '%',
            'date'         => date('d M Y', strtotime((string) $v)),
            'datetime'     => date('d M Y H:i', strtotime((string) $v)),
            default        => (string) $v,
        };
    }

    public static function download(array $report, string $format, string $basename): void
    {
        $file = preg_replace('/[^a-z0-9-]+/', '-', strtolower($basename)) . '.' . $format;
        match ($format) {
            'xlsx'  => self::xlsx($report, $file),
            'pdf'   => self::pdf($report, $file),
            default => self::csv($report, $file),
        };
    }

    private static function csv(array $report, string $file): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $safe = static function (array $row): array {
            return array_map(static function ($v) {
                $v = (string) $v;
                return ($v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) && !is_numeric($v)) ? "'" . $v : $v;
            }, $row);
        };
        fputcsv($out, $safe([$report['title'], $report['subtitle'] ?? '']));
        foreach ($report['cards'] ?? [] as [$label, $value, $type]) {
            fputcsv($out, $safe([$label, self::raw($value, $type)]));
        }
        foreach ($report['tables'] as $t) {
            fputcsv($out, []);
            if (!empty($t['title'])) {
                fputcsv($out, $safe([$t['title']]));
            }
            fputcsv($out, $safe(array_column($t['columns'], 1)));
            foreach ($t['rows'] as $r) {
                fputcsv($out, $safe(array_map(static fn($c) => self::raw($r[$c[0]] ?? null, $c[2]), $t['columns'])));
            }
            if (!empty($t['totals'])) {
                fputcsv($out, $safe(array_map(static fn($c) => self::raw($t['totals'][$c[0]] ?? null, $c[2]), $t['columns'])));
            }
        }
        fclose($out);
        exit;
    }

    private static function style(string $type, bool $bold): int
    {
        return match ($type) {
            'money', 'num' => $bold ? XlsxWriter::MONEY_BOLD : XlsxWriter::MONEY,
            'int'          => $bold ? XlsxWriter::INT_BOLD : XlsxWriter::INT,
            'pct'          => XlsxWriter::PCT,
            default        => $bold ? XlsxWriter::BOLD : XlsxWriter::TEXT,
        };
    }

    private static function xlsx(array $report, string $file): void
    {
        $x = new XlsxWriter();
        $first = true;
        foreach ($report['tables'] as $t) {
            $rows = [];
            if ($first) {
                $rows[] = [['v' => $report['title'], 's' => XlsxWriter::TITLE]];
                $rows[] = [['v' => $report['subtitle'] ?? '']];
                foreach ($report['cards'] ?? [] as [$label, $value, $type]) {
                    $rows[] = [['v' => $label, 's' => XlsxWriter::BOLD], ['v' => is_numeric($value) ? $value : self::raw($value, $type), 's' => self::style($type, false)]];
                }
                $rows[] = [];
            }
            if (!empty($t['title'])) {
                $rows[] = [['v' => $t['title'], 's' => XlsxWriter::BOLD]];
            }
            $rows[] = array_map(static fn($c) => ['v' => $c[1], 's' => XlsxWriter::HEAD], $t['columns']);
            $widths = array_map(static fn($c) => mb_strlen($c[1]) + 2, $t['columns']);
            foreach ($t['rows'] as $r) {
                $cells = [];
                foreach ($t['columns'] as $i => [$key, , $type]) {
                    $v = $r[$key] ?? null;
                    $v = in_array($type, ['money', 'num', 'int', 'pct'], true) ? ($v === null || $v === '' ? null : (float) $v) : self::raw($v, $type);
                    $cells[] = ['v' => $v, 's' => self::style($type, false)];
                    $widths[$i] = max($widths[$i], min(50, mb_strlen((string) $v) + 2));
                }
                $rows[] = $cells;
            }
            if (!empty($t['totals'])) {
                $rows[] = array_map(static function ($c) use ($t) {
                    $v = $t['totals'][$c[0]] ?? null;
                    return ['v' => in_array($c[2], ['money', 'num', 'int', 'pct'], true) && is_numeric($v) ? (float) $v : $v, 's' => self::style($c[2], true)];
                }, $t['columns']);
            }
            $x->addSheet($t['title'] ?: $report['title'], $rows, $widths);
            $first = false;
        }
        $x->download($file);
    }

    private static function pdf(array $report, string $file): void
    {
        $pdf = new PdfTable($report['title'], $report['subtitle'] ?? '');
        $pdf->cards(array_map(static fn($c) => [$c[0], ($c[2] === 'money' ? 'Tk ' : '') . self::pretty($c[1], $c[2])], $report['cards'] ?? []));
        foreach ($report['tables'] as $t) {
            $cols = array_map(static fn($c) => [$c[1], in_array($c[2], ['money', 'num', 'int', 'pct'], true) ? 'num' : 'text'], $t['columns']);
            $rows = array_map(static fn($r) => array_map(static fn($c) => self::pretty($r[$c[0]] ?? null, $c[2]), $t['columns']), $t['rows']);
            $totals = !empty($t['totals']) ? array_map(static fn($c) => self::pretty($t['totals'][$c[0]] ?? null, $c[2]), $t['columns']) : null;
            $pdf->table((string) ($t['title'] ?? ''), $cols, $rows, $totals);
        }
        foreach ($report['notes'] ?? [] as $n) {
            $pdf->note($n);
        }
        $pdf->download($file);
    }
}
