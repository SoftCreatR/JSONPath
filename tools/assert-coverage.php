<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

$file = $argv[1] ?? 'coverage.xml';
$report = \simplexml_load_file($file);

if ($report === false || !isset($report->project->metrics)) {
    \fwrite(\STDERR, "A valid Clover coverage report is required.\n");

    exit(1);
}

$metrics = $report->project->metrics;

foreach (['methods', 'statements'] as $kind) {
    $coveredKind = 'covered' . $kind;

    if ((int)$metrics[$kind] !== (int)$metrics[$coveredKind]) {
        \fwrite(\STDERR, "Coverage below 100% for {$kind}.\n");

        exit(1);
    }
}

// Clover has no coveredclasses metric: require every class to have no uncovered statements or methods.
foreach ($report->xpath('//file/class/metrics') ?: [] as $class) {
    if ((int)$class['methods'] !== (int)$class['coveredmethods'] || (int)$class['statements'] !== (int)$class['coveredstatements']) {
        \fwrite(\STDERR, "A class is not fully covered.\n");

        exit(1);
    }
}

\fwrite(\STDOUT, "Class, method, and line coverage: 100%.\n");
