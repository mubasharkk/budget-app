<?php

/**
 * Generate a self-contained SVG coverage badge from a Clover report.
 *
 * No third-party service required: it reads the project-level metrics from a
 * Clover XML file and writes a flat SVG badge to disk.
 *
 * Usage:
 *   php bin/coverage-badge.php [clover.xml] [badge.svg]
 *
 * Defaults: build/logs/clover.xml -> .github/badges/coverage.svg
 */
$cloverPath = $argv[1] ?? 'build/logs/clover.xml';
$badgePath = $argv[2] ?? '.github/badges/coverage.svg';

if (! is_file($cloverPath)) {
    fwrite(STDERR, "Clover report not found: {$cloverPath}\n");
    exit(1);
}

$xml = @simplexml_load_file($cloverPath);

if ($xml === false || ! isset($xml->project->metrics)) {
    fwrite(STDERR, "Unable to read project metrics from: {$cloverPath}\n");
    exit(1);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];

$percent = $statements > 0 ? ($covered / $statements) * 100 : 0.0;
$label = number_format($percent, $percent >= 100 ? 0 : 1, '.', '').'%';

$color = match (true) {
    $percent >= 90 => '#4c1',   // brightgreen
    $percent >= 80 => '#97ca00', // green
    $percent >= 70 => '#a4a61d', // yellowgreen
    $percent >= 60 => '#dfb317', // yellow
    $percent >= 50 => '#fe7d37', // orange
    default => '#e05d44',        // red
};

$svg = renderBadge('coverage', $label, $color);

$dir = dirname($badgePath);

if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
    fwrite(STDERR, "Unable to create directory: {$dir}\n");
    exit(1);
}

file_put_contents($badgePath, $svg);

fwrite(STDOUT, "Coverage: {$label} ({$covered}/{$statements} statements) -> {$badgePath}\n");

/**
 * Render a flat "label | value" SVG badge, approximating shields.io styling.
 */
function renderBadge(string $label, string $value, string $color): string
{
    $labelWidth = textWidth($label) + 10;
    $valueWidth = textWidth($value) + 10;
    $totalWidth = $labelWidth + $valueWidth;

    $labelX = ($labelWidth / 2) * 10;
    $valueX = ($labelWidth + $valueWidth / 2) * 10;
    $labelTextWidth = (textWidth($label)) * 10;
    $valueTextWidth = (textWidth($value)) * 10;

    $labelEsc = htmlspecialchars($label, ENT_XML1);
    $valueEsc = htmlspecialchars($value, ENT_XML1);

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="{$totalWidth}" height="20" role="img" aria-label="{$labelEsc}: {$valueEsc}">
  <title>{$labelEsc}: {$valueEsc}</title>
  <linearGradient id="s" x2="0" y2="100%">
    <stop offset="0" stop-color="#bbb" stop-opacity=".1"/>
    <stop offset="1" stop-opacity=".1"/>
  </linearGradient>
  <clipPath id="r"><rect width="{$totalWidth}" height="20" rx="3" fill="#fff"/></clipPath>
  <g clip-path="url(#r)">
    <rect width="{$labelWidth}" height="20" fill="#555"/>
    <rect x="{$labelWidth}" width="{$valueWidth}" height="20" fill="{$color}"/>
    <rect width="{$totalWidth}" height="20" fill="url(#s)"/>
  </g>
  <g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" text-rendering="geometricPrecision" font-size="110">
    <text aria-hidden="true" x="{$labelX}" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="{$labelTextWidth}">{$labelEsc}</text>
    <text x="{$labelX}" y="140" transform="scale(.1)" fill="#fff" textLength="{$labelTextWidth}">{$labelEsc}</text>
    <text aria-hidden="true" x="{$valueX}" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="{$valueTextWidth}">{$valueEsc}</text>
    <text x="{$valueX}" y="140" transform="scale(.1)" fill="#fff" textLength="{$valueTextWidth}">{$valueEsc}</text>
  </g>
</svg>

SVG;
}

/**
 * Approximate rendered text width (in px) for the badge font at size 11.
 */
function textWidth(string $text): int
{
    return (int) round(strlen($text) * 6.5);
}
