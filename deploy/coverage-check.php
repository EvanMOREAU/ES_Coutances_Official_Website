<?php

/**
 * Lit le rapport Clover de PHPUnit, affiche le taux de couverture, l'ajoute au résumé du job GitHub
 * et échoue s'il est inférieur au seuil COVERAGE_MIN (en %).
 *
 * Usage : php deploy/coverage-check.php var/clover.xml
 */
$file = $argv[1] ?? 'var/clover.xml';
$xml = is_file($file) ? simplexml_load_file($file) : false;
if (false === $xml) {
    fwrite(STDERR, "Rapport de couverture introuvable : $file\n");
    exit(1);
}

$metrics = $xml->project->metrics;
$rate = 100 * (int) $metrics['coveredstatements'] / max(1, (int) $metrics['statements']);
$min = (float) getenv('COVERAGE_MIN');

printf("Couverture : %.1f %% (seuil %.1f %%)\n", $rate, $min);
if ($summary = getenv('GITHUB_STEP_SUMMARY')) {
    file_put_contents($summary, sprintf("### Couverture des tests : %.1f %% (seuil %.1f %%)\n", $rate, $min), FILE_APPEND);
}

exit($rate < $min ? 1 : 0);
