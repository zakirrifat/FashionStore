<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /**
     * Stream a CSV download. $rows is any iterable of arrays (use lazy() for big tables).
     * Adds a UTF-8 BOM so Excel shows accents correctly.
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row), escape: '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stop spreadsheet formula injection (cells starting with = + - @ are run as formulas by Excel). */
    public static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }

    /** Read a CSV file into an array of rows keyed by lower-case header. */
    public static function read(string $path): array
    {
        $handle = fopen($path, 'r');
        $first = fgets($handle);
        // Detect ; separated files (Excel in some regions saves CSV with semicolons).
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($handle);

        $headers = null;
        $rows = [];
        while (($data = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if ($headers === null) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0] ?? '');
                $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $data);

                continue;
            }
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }
            $row = [];
            foreach ($headers as $i => $h) {
                $value = isset($data[$i]) ? trim($data[$i]) : '';
                // Undo our own formula-protection prefix.
                $row[$h] = str_starts_with($value, "'") && strlen($value) > 1 && in_array($value[1], ['=', '+', '-', '@'], true) ? substr($value, 1) : $value;
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
