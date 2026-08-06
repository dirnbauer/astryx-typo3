<?php

declare(strict_types=1);

/**
 * These tests read the extension's own data files and generated output; none
 * of them boots TYPO3, so Astryx's own Composer autoloader is enough.
 */
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Install Astryx dependencies first (composer install in this repository).\n");
    exit(1);
}

require $autoload;
