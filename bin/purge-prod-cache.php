<?php

// Lancé par Composer avant les auto-scripts : supprime le cache de production pour que « cache:clear »
// recompile le conteneur à partir du code qui vient d'être installé. Sans cela, il démarre sur l'ancien
// conteneur et plante dès qu'un constructeur de service a changé (ArgumentCountError).
require dirname(__DIR__).'/vendor/autoload.php';

(new Symfony\Component\Filesystem\Filesystem())->remove(dirname(__DIR__).'/var/cache/prod');
