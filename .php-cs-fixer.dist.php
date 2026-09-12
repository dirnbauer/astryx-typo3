<?php

declare(strict_types=1);

use TYPO3\CodingStandards\CsFixerConfig;

/*
 * Coding standards of the TYPO3 community (typo3/coding-standards), applied to
 * every PHP file this extension owns: Classes, Tests and the PHP build scripts.
 *
 *   composer cgl          fix in place
 *   composer cgl:check    report only, changes nothing (this is what CI runs)
 *
 * Fluid templates are not PHP and are deliberately out of scope.
 */

$cacheDirectory = __DIR__ . '/var';
if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0o775, true) && !is_dir($cacheDirectory)) {
    throw new RuntimeException(sprintf('Unable to create the cache directory "%s".', $cacheDirectory), 1757740801);
}

$config = CsFixerConfig::create();
$config->setCacheFile($cacheDirectory . '/.php-cs-fixer.cache');
$config->getFinder()
    ->in(__DIR__ . '/Classes')
    ->in(__DIR__ . '/Tests')
    ->in(__DIR__ . '/Build/Scripts')
    ->append([__FILE__]);

return $config;
