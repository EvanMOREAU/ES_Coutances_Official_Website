<?php

/**
 * Lit le rapport Clover de PHPUnit, affiche le taux de couverture, l'ajoute au résumé du job GitHub
 * et échoue s'il est inférieur au plancher.
 *
 * Le plancher vient de COVERAGE_MIN (variable d'environnement) ou, à défaut, de deploy/coverage-floor.txt
 * (en %). C'est un « cliquet » : la couverture ne doit jamais baisser. Quand le taux mesuré dépasse nettement le
 * plancher, le script propose la nouvelle valeur ; il suffit de la recopier dans deploy/coverage-floor.txt.
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

$floorFile = __DIR__.'/coverage-floor.txt';
$env = getenv('COVERAGE_MIN');
$min = false !== $env && '' !== $env ? (float) $env : (is_file($floorFile) ? (float) trim((string) file_get_contents($floorFile)) : 0.0);

$lines = [sprintf('Couverture : %.1f %% (plancher %.1f %%)', $rate, $min)];
$suggestion = floor($rate) - 1;
if ($suggestion > $min) {
    $lines[] = sprintf('Le plancher peut être relevé : mettre %d dans deploy/coverage-floor.txt.', $suggestion);
}
echo implode("\n", $lines), "\n";
if ($summary = getenv('GITHUB_STEP_SUMMARY')) {
    file_put_contents($summary, '### '.implode("\n\n", $lines)."\n", FILE_APPEND);
}

exit($rate < $min ? 1 : 0);
