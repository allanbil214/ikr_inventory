<?php
/**
 * helpers.php
 * Small shared helper functions.
 */

/**
 * Appends a filemtime()-based version query string to a public asset path,
 * so browsers auto-pick-up changed CSS/JS without a hard refresh.
 *
 * Usage: <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
 */
function asset(string $path): string
{
    $relative = ltrim($path, '/');
    $fullPath = __DIR__ . '/../../public/' . $relative;

    $version = file_exists($fullPath) ? filemtime($fullPath) : time();

    return $relative . '?v=' . $version;
}
