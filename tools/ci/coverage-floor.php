#!/usr/bin/env php
<?php

/**
 * Fail when line coverage in a Clover report drops below a floor.
 *
 * Usage: php tools/ci/coverage-floor.php build/coverage/clover.xml 60
 */
[$script, $clover, $floor] = $argv + [null, null, null];

if (! $clover || ! is_file($clover) || ! is_numeric($floor)) {
    fwrite(STDERR, "usage: coverage-floor.php <clover.xml> <min-percent>\n");
    exit(2);
}

$xml = simplexml_load_file($clover);
$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $statements === 0 ? 0.0 : round($covered / $statements * 100, 2);

printf("Line coverage: %.2f%% (%d/%d statements); floor %.2f%%\n", $percent, $covered, $statements, (float) $floor);

exit($percent + 0.0001 < (float) $floor ? 1 : 0);
