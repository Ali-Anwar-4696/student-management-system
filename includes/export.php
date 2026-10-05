<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CSV export helper
|--------------------------------------------------------------------------
|
| Usage (after authentication/authorization has already been enforced):
|
|   require_once __DIR__ . '/../includes/export.php';
|   export_csv('students.csv', ['ID', 'Name'], $rows);
|
| - Streams straight to the output buffer and exits.
| - Adds a UTF-8 BOM so spreadsheet apps detect the encoding.
| - Guards against spreadsheet formula injection (OWASP): cells that
|   start with =, +, @ or - (and are not plain numbers) are prefixed
|   with a single quote so they render as text instead of executing.
|
| NOTE: export endpoints are read-only downloads, so they keep the GET
| method. They still require a logged-in, authorized session because
| every caller runs requireAdmin()/equivalent before calling this.
|
*/

if (!function_exists('export_csv')) {

    function export_csv(
        string $filename,
        array $headers,
        iterable $rows
    ): never {

        // Header-safe filename (no quotes, CR/LF or path separators).
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

        if ($safe === null || $safe === '' || $safe === '.' || $safe === '..') {
            $safe = 'export.csv';
        }

        // Drop anything a caller may have printed earlier (whitespace,
        // stray output) so the CSV body stays clean.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header(
            'Content-Disposition: attachment; filename="' . $safe . '"'
        );
        header('Pragma: no-cache');
        header('Cache-Control: no-store, max-age=0, must-revalidate');

        $out = fopen('php://output', 'w');

        if ($out === false) {
            http_response_code(500);
            exit('Unable to open the output stream.');
        }

        // UTF-8 byte order mark — keeps Excel happy with UTF-8 text.
        fwrite($out, "\xEF\xBB\xBF");

        $protect = static function (mixed $value): string {
            $value = (string) $value;

            if (
                $value !== ''
                && strpbrk($value[0], '=+@-') !== false
                && !is_numeric($value)
            ) {
                return "'" . $value;
            }

            return $value;
        };

        fputcsv($out, array_map($protect, $headers));

        foreach ($rows as $row) {
            fputcsv($out, array_map($protect, array_values((array) $row)));
        }

        fclose($out);

        exit;
    }

}
