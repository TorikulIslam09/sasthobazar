<?php
/**
 * SqlImporter - splits a .sql file into statements (quote/comment aware) and runs them one by one,
 * so a failing statement reports exactly which one failed.
 */
declare(strict_types=1);

final class SqlImporter
{
    /** @return int number of statements executed */
    public static function runFile(PDO $pdo, string $file): int
    {
        if (!is_file($file)) {
            throw new RuntimeException('SQL file not found: ' . $file);
        }
        $count = 0;
        foreach (self::split((string) file_get_contents($file)) as $i => $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                $preview = mb_substr(preg_replace('/\s+/', ' ', $stmt), 0, 200);
                throw new RuntimeException(sprintf('%s - statement #%d failed: %s ... => %s', basename($file), $i + 1, $preview, $e->getMessage()), 0, $e);
            }
            $count++;
        }
        return $count;
    }

    /** Split SQL text into statements on ';' outside quotes and comments. */
    public static function split(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql); // strip UTF-8 BOM
        $statements = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote !== null) {
                $buf .= $c;
                if ($c === '\\' && $quote !== '`') {        // backslash escape
                    $buf .= $next;
                    $i++;
                } elseif ($c === $quote) {
                    if ($next === $quote) {                 // doubled quote ''
                        $buf .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            // -- comment / # comment
            if (($c === '-' && $next === '-' && in_array($sql[$i + 2] ?? ' ', [' ', "\t", "\n", "\r"], true)) || $c === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                $buf .= "\n";
                continue;
            }
            // /* block comment */
            if ($c === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
                continue;
            }

            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
                $buf .= $c;
                continue;
            }

            if ($c === ';') {
                if (trim($buf) !== '') {
                    $statements[] = trim($buf);
                }
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        if (trim($buf) !== '') {
            $statements[] = trim($buf);
        }
        return $statements;
    }
}
