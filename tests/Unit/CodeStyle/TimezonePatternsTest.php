<?php

/**
 * Garde anti-regression : config('app.timezone') vaut toujours 'UTC' (voir
 * config/app.php), donc "config('app.timezone') ?: 'Africa/Douala'" ou
 * "config('app.timezone', 'Africa/Douala')" ne retombent JAMAIS sur le
 * fallback -- la cle existe et n'est jamais vide. C'est exactement le bug
 * silencieux trouve et corrige dans ControlGpsController/DashboardLeaseService
 * lors du correctif timezone d'aout 2026 : le code affichait UTC brut en
 * pensant afficher Douala. Ce test empeche que le motif revienne.
 *
 * Le fuseau d'affichage doit passer par config('app.display_timezone'), pas
 * par un pseudo-fallback sur config('app.timezone').
 */
function scannableTimezoneFiles(): array
{
    $roots = [
        base_path('app'),
        base_path('resources/views'),
    ];

    $files = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.(php|blade\.php)$/', $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

function isCommentLine(string $line): bool
{
    $trimmed = ltrim($line);

    return $trimmed === ''
        || str_starts_with($trimmed, '//')
        || str_starts_with($trimmed, '*')
        || str_starts_with($trimmed, '/*')
        || str_starts_with($trimmed, '{{--')
        || str_starts_with($trimmed, '#');
}

it("ne contient plus le faux fallback config('app.timezone') ?: 'Africa/Douala'", function () {
    $offenders = [];

    foreach (scannableTimezoneFiles() as $path) {
        $lines = file($path);

        foreach ($lines as $lineNumber => $line) {
            if (isCommentLine($line)) {
                continue;
            }

            if (preg_match("/config\(\s*['\"]app\.timezone['\"]\s*\)\s*\?:/", $line)) {
                $offenders[] = $path . ':' . ($lineNumber + 1) . ' — ' . trim($line);
            }
        }
    }

    expect($offenders)->toBe([]);
});

it("ne contient plus le faux fallback config('app.timezone', 'Africa/Douala')", function () {
    $offenders = [];

    foreach (scannableTimezoneFiles() as $path) {
        $lines = file($path);

        foreach ($lines as $lineNumber => $line) {
            if (isCommentLine($line)) {
                continue;
            }

            if (preg_match("/config\(\s*['\"]app\.timezone['\"]\s*,/", $line)) {
                $offenders[] = $path . ':' . ($lineNumber + 1) . ' — ' . trim($line);
            }
        }
    }

    expect($offenders)->toBe([]);
});
