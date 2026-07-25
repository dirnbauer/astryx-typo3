<?php

declare(strict_types=1);

/**
 * These tests read the extension's own data files and generated output; none
 * of them boots TYPO3, so the lab's autoloader is enough.
 */
$autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Install the lab's dependencies first (composer install in the project root).\n");
    exit(1);
}

require $autoload;
